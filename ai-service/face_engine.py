"""Single-image inference. Image bytes and embeddings are never persisted or logged."""
from io import BytesIO
import warnings

import cv2
import numpy as np
from PIL import Image, UnidentifiedImageError

from config import Settings
from model_runtime import ModelRuntime


class FaceError(Exception):
    def __init__(self, code: str, message: str, status: int = 422):
        self.code, self.message, self.status = code, message, status
        super().__init__(message)


def normalize_embedding(value) -> np.ndarray:
    try:
        vector = np.asarray(value, dtype=np.float64)
    except (ValueError, TypeError):
        raise FaceError('invalid_embedding', 'Model menghasilkan embedding tidak valid.', 503) from None
    if vector.shape != (512,) or not np.isfinite(vector).all():
        raise FaceError('invalid_embedding', 'Model menghasilkan embedding tidak valid.', 503)
    norm = np.linalg.norm(vector)
    if not np.isfinite(norm) or norm <= 1e-12:
        raise FaceError('invalid_embedding', 'Model menghasilkan embedding tidak valid.', 503)
    return vector / norm


class FaceEngine:
    def __init__(self, settings: Settings, runtime: ModelRuntime):
        self.settings, self.runtime = settings, runtime

    def decode(self, payload: bytes, content_type: str) -> np.ndarray:
        expected = {'image/jpeg': 'JPEG', 'image/png': 'PNG', 'image/webp': 'WEBP'}
        if content_type not in expected:
            raise FaceError('unsupported_image_type', 'Gunakan gambar JPEG, PNG, atau WebP.', 415)
        if not payload:
            raise FaceError('invalid_image', 'Gambar kosong atau tidak valid.')
        if len(payload) > self.settings.ai_max_image_bytes:
            raise FaceError('image_too_large', 'Ukuran gambar melebihi batas.', 413)
        try:
            # Read the encoded header before allocating decoded OpenCV pixels.
            with warnings.catch_warnings():
                warnings.simplefilter('error', Image.DecompressionBombWarning)
                with Image.open(BytesIO(payload)) as header:
                    width, height = header.size
                    if header.format != expected[content_type] or getattr(header, 'n_frames', 1) != 1:
                        raise FaceError('invalid_image', 'Format gambar tidak sesuai atau gambar animasi.')
                    if min(width, height) < 32 or max(width, height) > self.settings.ai_max_image_side or width * height > self.settings.ai_max_image_pixels:
                        raise FaceError('invalid_image_dimensions', 'Dimensi gambar tidak didukung.')
                    header.verify()
            image = cv2.imdecode(np.frombuffer(payload, dtype=np.uint8), cv2.IMREAD_COLOR | cv2.IMREAD_IGNORE_ORIENTATION)
        except FaceError:
            raise
        except (UnidentifiedImageError, OSError, ValueError, SyntaxError, Image.DecompressionBombError, Image.DecompressionBombWarning, cv2.error):
            raise FaceError('invalid_image', 'Gambar tidak dapat dibaca.') from None
        if image is None or image.shape != (height, width, 3):
            raise FaceError('invalid_image', 'Gambar tidak dapat dibaca.')
        return image

    def extract(self, payload: bytes, content_type: str) -> dict:
        image = self.decode(payload, content_type)
        if not self.runtime.ready or self.runtime.analysis is None:
            raise FaceError('service_unavailable', 'Sistem sedang mengalami gangguan. Silakan coba kembali.', 503)
        try:
            analysis = self.runtime.analysis
            # max_num=0 is deliberate: never silently choose one of several faces.
            boxes, landmarks = analysis.det_model.detect(image, max_num=0, metric='default')
            if len(boxes) == 0:
                raise FaceError('no_face_detected', 'Wajah tidak terdeteksi. Silakan posisikan wajah di depan kamera.')
            if len(boxes) != 1:
                raise FaceError('multiple_faces_detected', 'Terdeteksi lebih dari satu wajah. Pastikan hanya satu orang di depan kamera.')
            quality = self.validate_quality(image, boxes[0], landmarks)
            from insightface.app.common import Face
            face = Face(bbox=boxes[0, :4], kps=landmarks[0], det_score=float(boxes[0, 4]))
            # InsightFace recognition.get performs five-landmark alignment internally.
            raw = analysis.models['recognition'].get(image, face)
            embedding = normalize_embedding(raw)
            return {'status': 'ok', 'model_name': 'buffalo_m',
                    'model_identifier': self.runtime.identifier, 'embedding_dimension': 512,
                    'embedding': embedding.tolist(), 'quality': quality}
        except FaceError:
            raise
        except Exception:
            # Do not expose/log model exceptions or byte/vector payloads.
            raise FaceError('inference_failed', 'Sistem sedang mengalami gangguan. Silakan coba kembali.', 503) from None

    def validate_quality(self, image: np.ndarray, box, landmarks) -> dict:
        height, width = image.shape[:2]
        if np.shape(box) != (5,) or not np.isfinite(box).all():
            raise FaceError('invalid_detection', 'Hasil deteksi wajah tidak valid.', 503)
        x1, y1, x2, y2, score = map(float, box)
        if score < self.settings.face_min_detection_score:
            raise FaceError('low_detection_confidence', 'Wajah kurang jelas. Silakan coba kembali.')
        if x1 < 0 or y1 < 0 or x2 > width or y2 > height or x2 <= x1 or y2 <= y1:
            raise FaceError('face_out_of_frame', 'Pastikan seluruh wajah berada di dalam bingkai.')
        if min(x2 - x1, y2 - y1) < self.settings.face_min_size:
            raise FaceError('face_too_small', 'Wajah terlalu kecil. Silakan mendekat ke kamera.')
        if landmarks is None or np.shape(landmarks) != (1, 5, 2) or not np.isfinite(landmarks).all():
            raise FaceError('invalid_landmarks', 'Posisi wajah tidak memadai. Hadapkan wajah ke kamera.')
        points = landmarks[0]
        if np.any(points[:, 0] < x1) or np.any(points[:, 0] > x2) or np.any(points[:, 1] < y1) or np.any(points[:, 1] > y2) or len(np.unique(points, axis=0)) != 5 or np.linalg.matrix_rank(points - points.mean(axis=0)) < 2:
            raise FaceError('invalid_landmarks', 'Posisi wajah tidak memadai. Hadapkan wajah ke kamera.')
        crop = image[int(y1):int(y2), int(x1):int(x2)]
        gray = cv2.cvtColor(crop, cv2.COLOR_BGR2GRAY)
        # Fixed quality resolution makes the blur heuristic less dependent on capture size.
        gray = cv2.resize(gray, (112, 112), interpolation=cv2.INTER_AREA)
        brightness = float(gray.mean())
        blur = float(cv2.Laplacian(gray, cv2.CV_64F).var())
        if brightness < self.settings.face_min_brightness or brightness > self.settings.face_max_brightness:
            raise FaceError('poor_lighting', 'Pencahayaan wajah tidak memadai. Gunakan cahaya yang merata.')
        if blur < self.settings.face_min_blur_variance:
            raise FaceError('face_too_blurry', 'Wajah terlalu blur. Tahan posisi dan coba kembali.')
        return {'detection_score': score, 'face_width': round(x2 - x1, 2),
                'face_height': round(y2 - y1, 2), 'blur_variance': round(blur, 2),
                'brightness': round(brightness, 2)}
