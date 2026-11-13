from io import BytesIO
from types import SimpleNamespace

import cv2
from fastapi.testclient import TestClient
import numpy as np
from PIL import Image
import pytest

from config import Settings
from face_engine import FaceEngine, FaceError, normalize_embedding
from main import create_app

KEY = 'synthetic-key-' + 'x' * 40
HEADERS = {'X-API-Key': KEY, 'Content-Type': 'image/png'}


def config(**values):
    return Settings(_env_file=None, ai_service_api_key=KEY, **values)


def payload(image=None, extension='.png'):
    if image is None:
        image = np.random.default_rng(42).integers(60, 195, size=(240, 240, 3), dtype=np.uint8)
    return cv2.imencode(extension, image)[1].tobytes()


class Runtime:
    def __init__(self, count=1, box=None, points=None, vector=None, failure=False):
        self.ready = True
        self.identifier = 'buffalo_m:synthetic-test-double'
        self.code = 'ready'
        self.recognition_calls = 0
        boxes = np.array([box or [30, 30, 210, 210, 0.99]] * count, dtype=np.float32).reshape(count, 5)
        landmarks = np.array([points or [[80, 85], [160, 85], [120, 120], [90, 165], [150, 165]]] * count, dtype=np.float32).reshape(count, 5, 2)

        def detect(image, max_num, metric):
            assert max_num == 0
            if failure:
                raise RuntimeError('SENSITIVE_MODEL_DETAILS')
            return boxes, landmarks

        def recognize(image, face):
            self.recognition_calls += 1
            return np.arange(1, 513, dtype=np.float32) if vector is None else vector

        self.analysis = SimpleNamespace(det_model=SimpleNamespace(detect=detect), models={'recognition': SimpleNamespace(get=recognize)})

    def load(self, settings):
        pass

    def close(self):
        self.ready = False


def test_single_face_returns_normalized_embedding_and_metadata_without_writes(tmp_path, monkeypatch):
    monkeypatch.chdir(tmp_path)
    runtime = Runtime()
    with TestClient(create_app(config(), runtime)) as client:
        response = client.post('/face/extract-embedding', content=payload(), headers=HEADERS)
        assert response.status_code == 200
        body = response.json()
        assert body['model_name'] == 'buffalo_m'
        assert body['embedding_dimension'] == 512
        assert len(body['embedding']) == 512
        assert np.linalg.norm(body['embedding']) == pytest.approx(1)
        assert runtime.recognition_calls == 1
        assert 'employee_id' not in body
        assert response.headers['cache-control'] == 'no-store'
    assert list(tmp_path.iterdir()) == []


@pytest.mark.parametrize(('runtime', 'image', 'code'), [
    (lambda: Runtime(count=0), None, 'no_face_detected'),
    (lambda: Runtime(count=2), None, 'multiple_faces_detected'),
    (lambda: Runtime(box=[30, 30, 70, 70, .99]), None, 'face_too_small'),
    (lambda: Runtime(box=[-10, 30, 210, 210, .99]), None, 'face_out_of_frame'),
    (lambda: Runtime(box=[30, 30, 210, 210, .55]), None, 'low_detection_confidence'),
    (lambda: Runtime(points=[[0, 0]] * 5), None, 'invalid_landmarks'),
    (lambda: Runtime(), np.full((240, 240, 3), 120, dtype=np.uint8), 'face_too_blurry'),
    (lambda: Runtime(), np.zeros((240, 240, 3), dtype=np.uint8), 'poor_lighting'),
    (lambda: Runtime(), np.full((240, 240, 3), 255, dtype=np.uint8), 'poor_lighting'),
])
def test_face_quality_rejections_do_not_extract_or_leak_vectors(runtime, image, code):
    runtime = runtime()
    with TestClient(create_app(config(), runtime)) as client:
        response = client.post('/face/extract-embedding', content=payload(image), headers=HEADERS)
        assert response.status_code == 422
        assert response.json()['error']['code'] == code
        assert 'embedding' not in response.json()
        assert runtime.recognition_calls == 0


@pytest.mark.parametrize('value', [[], np.zeros(512), np.ones(511), np.full(512, np.nan), np.full(512, np.inf), np.ones((1, 512))])
def test_invalid_embeddings_fail_closed(value):
    with pytest.raises(FaceError, match='embedding tidak valid'):
        normalize_embedding(value)


def test_normalization_and_configurable_quality():
    assert np.linalg.norm(normalize_embedding(np.ones(512) * 5)) == pytest.approx(1)
    image = np.full((240, 240, 3), 120, dtype=np.uint8)
    assert FaceEngine(config(face_min_blur_variance=0), Runtime()).extract(payload(image), 'image/png')['status'] == 'ok'
    with pytest.raises(FaceError) as error:
        FaceEngine(config(face_min_size=200), Runtime()).extract(payload(), 'image/png')
    assert error.value.code == 'face_too_small'


@pytest.mark.parametrize(('data', 'mime', 'status', 'code'), [
    (b'', 'image/png', 422, 'invalid_image'),
    (b'not-image', 'image/png', 422, 'invalid_image'),
    (b'{}', 'application/json', 415, 'unsupported_image_type'),
    (payload(), 'image/jpeg', 422, 'invalid_image'),
    (payload(np.zeros((20, 20, 3), dtype=np.uint8)), 'image/png', 422, 'invalid_image_dimensions'),
])
def test_image_validation(data, mime, status, code):
    with TestClient(create_app(config(), Runtime())) as client:
        response = client.post('/face/extract-embedding', content=data, headers={**HEADERS, 'Content-Type': mime})
        assert response.status_code == status
        assert response.json()['error']['code'] == code


def test_dimensions_checked_before_opencv_decode(monkeypatch):
    raw = BytesIO()
    Image.new('RGB', (4100, 40)).save(raw, format='PNG')
    monkeypatch.setattr(cv2, 'imdecode', lambda *args: pytest.fail('Oversized image reached decoder'))
    with pytest.raises(FaceError) as error:
        FaceEngine(config(), Runtime()).decode(raw.getvalue(), 'image/png')
    assert error.value.code == 'invalid_image_dimensions'


def test_size_limit_checks_header_and_stream_without_content_length():
    with TestClient(create_app(config(ai_max_image_bytes=1024), Runtime())) as client:
        for content in (b'x' * 1025, iter([b'x' * 700, b'y' * 700])):
            response = client.post('/face/extract-embedding', content=content, headers=HEADERS)
            assert response.status_code == 413
            assert response.json()['error']['code'] == 'image_too_large'


def test_auth_rate_limit_service_failure_and_no_sensitive_error():
    with TestClient(create_app(config(ai_extract_requests_per_minute=1), Runtime(failure=True))) as client:
        assert client.post('/face/extract-embedding', content=payload()).status_code == 401
        response = client.post('/face/extract-embedding', content=payload(), headers=HEADERS)
        assert response.status_code == 503
        assert response.json()['error']['code'] == 'inference_failed'
        assert 'SENSITIVE' not in response.text
        assert client.post('/face/extract-embedding', content=payload(), headers=HEADERS).status_code == 429
        assert client.get('/health', headers=HEADERS).status_code == 200


def test_invalid_inference_vector_never_reaches_response():
    with TestClient(create_app(config(), Runtime(vector=np.zeros(512)))) as client:
        response = client.post('/face/extract-embedding', content=payload(), headers=HEADERS)
        assert response.status_code == 503
        assert response.json()['error']['code'] == 'invalid_embedding'


def test_model_unavailable_is_503():
    runtime = Runtime()
    runtime.ready = False
    with TestClient(create_app(config(), runtime)) as client:
        response = client.post('/face/extract-embedding', content=payload(), headers=HEADERS)
        assert response.status_code == 503
        assert response.json()['error']['code'] == 'service_unavailable'


@pytest.mark.parametrize(('extension', 'mime'), [('.jpg', 'image/jpeg'), ('.webp', 'image/webp')])
def test_other_supported_image_types(extension, mime):
    result = FaceEngine(config(), Runtime()).extract(payload(extension=extension), mime)
    assert result['status'] == 'ok'


def test_rejects_invalid_length_encoding_and_animation():
    raw = BytesIO()
    Image.new('RGB', (100, 100)).save(raw, format='PNG', save_all=True,
        append_images=[Image.new('RGB', (100, 100), 'white')], duration=100, loop=0)
    with TestClient(create_app(config(), Runtime())) as client:
        for length in ('wrong', '-1'):
            response = client.post('/face/extract-embedding', content=b'x', headers={**HEADERS, 'Content-Length': length})
            assert response.status_code == 400
        response = client.post('/face/extract-embedding', content=b'x', headers={**HEADERS, 'Content-Length': '9' * 5000})
        assert response.status_code == 413
        response = client.post('/face/extract-embedding', content=payload(), headers={**HEADERS, 'Content-Encoding': 'gzip'})
        assert response.status_code == 415
        response = client.post('/face/extract-embedding', content=raw.getvalue(), headers=HEADERS)
        assert response.status_code == 422
        assert response.json()['error']['code'] == 'invalid_image'


def test_slow_stream_is_timed_out():
    import anyio
    import httpx

    app = create_app(config(ai_body_timeout=.01), Runtime())
    with TestClient(app):
        async def run():
            async def slow():
                yield b'x'
                await anyio.sleep(.05)
                yield b'y'
            async with httpx.AsyncClient(transport=httpx.ASGITransport(app=app), base_url='http://test') as client:
                response = await client.post('/face/extract-embedding', content=slow(), headers=HEADERS)
                assert response.status_code == 408
                assert response.json()['error']['code'] == 'request_timeout'
        anyio.run(run)


def test_concurrent_inference_is_rejected_and_lock_recovers():
    from concurrent.futures import ThreadPoolExecutor
    from threading import Event

    runtime = Runtime()
    entered, release = Event(), Event()
    original = runtime.analysis.det_model.detect

    def slow_detect(*args, **kwargs):
        entered.set()
        assert release.wait(5)
        return original(*args, **kwargs)

    runtime.analysis.det_model.detect = slow_detect
    with TestClient(create_app(config(), runtime)) as client, ThreadPoolExecutor(max_workers=1) as pool:
        first = pool.submit(client.post, '/face/extract-embedding', content=payload(), headers=HEADERS)
        try:
            assert entered.wait(5)
            second = client.post('/face/extract-embedding', content=payload(), headers=HEADERS)
            assert second.status_code == 503
            assert second.json()['error']['code'] == 'service_busy'
        finally:
            release.set()
        assert first.result(timeout=5).status_code == 200
        assert client.post('/face/extract-embedding', content=payload(), headers=HEADERS).status_code == 200
