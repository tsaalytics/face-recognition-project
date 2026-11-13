"""Load only locally provisioned buffalo_m weights; no request-time download."""
import hashlib
import json
from pathlib import Path
from typing import TYPE_CHECKING

if TYPE_CHECKING:
    from config import Settings

MODEL_FILES = ('det_2.5g.onnx', 'w600k_r50.onnx')
ARCHIVE_SHA256 = 'd98264bd8f2dc75cbc2ddce2a14e636e02bb857b3051c234b737bf3b614edca9'
MODEL_URL = 'https://github.com/deepinsight/insightface/releases/download/model-zoo/buffalo_m.zip'


def file_digest(path: Path) -> str:
    digest = hashlib.sha256()
    with path.open('rb') as stream:
        for chunk in iter(lambda: stream.read(1024 * 1024), b''):
            digest.update(chunk)
    return digest.hexdigest()


class ModelRuntime:
    def __init__(self) -> None:
        self.analysis = None
        self.ready = False
        self.code = 'model_not_loaded'
        self.identifier = None

    def load(self, settings: 'Settings') -> None:
        self.analysis = None
        self.ready = False
        self.identifier = None
        folder = settings.face_model_root.resolve() / 'models' / 'buffalo_m'
        if not all((folder / name).is_file() for name in MODEL_FILES):
            self.code = 'model_files_missing'
            return
        try:
            provenance = json.loads((folder / 'facetrack-source.json').read_text())
            if provenance['archive_sha256'] != ARCHIVE_SHA256:
                raise ValueError('Unexpected model archive')
            hashes = {name: file_digest(folder / name) for name in MODEL_FILES}
            if hashes != provenance['files']:
                raise ValueError('Model file integrity mismatch')
            import onnxruntime as ort
            import numpy as np
            from insightface.app import FaceAnalysis

            analysis = FaceAnalysis(
                name='buffalo_m', root=str(settings.face_model_root.resolve()),
                allowed_modules=['detection', 'recognition'],
                providers=['CPUExecutionProvider'],
            )
            analysis.prepare(ctx_id=-1, det_size=(640, 640))
            if set(analysis.models) != {'detection', 'recognition'}:
                raise ValueError('Required model tasks are missing')
            for model in analysis.models.values():
                if model.session.get_providers() != ['CPUExecutionProvider']:
                    raise ValueError('Unexpected execution provider')
            if 'CPUExecutionProvider' not in ort.get_available_providers():
                raise ValueError('CPU provider unavailable')
            # Synthetic blank input only: verifies detection inference without participant data.
            analysis.get(np.zeros((640, 640, 3), dtype=np.uint8))
            vector = analysis.models['recognition'].get_feat(
                np.zeros((112, 112, 3), dtype=np.uint8)
            )
            if vector.shape != (1, 512) or not np.isfinite(vector).all():
                raise ValueError('Unexpected recognition output')
            self.analysis = analysis
            self.identifier = 'buffalo_m:sha256:' + hashlib.sha256(
                ''.join(hashes[name] for name in MODEL_FILES).encode()
            ).hexdigest()
            self.ready = True
            self.code = 'ready'
        except Exception:
            # Do not log exception payloads, paths, request bodies, or model outputs.
            self.analysis = None
            self.ready = False
            self.code = 'model_load_failed'

    def close(self) -> None:
        self.analysis = None
        self.ready = False

