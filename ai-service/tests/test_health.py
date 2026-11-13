from fastapi.testclient import TestClient
from pydantic import ValidationError
import pytest

from config import Settings
from main import create_app
from model_runtime import ModelRuntime

KEY = 'synthetic-test-key-' + 'x' * 32


class FakeRuntime:
    ready = True
    code = 'ready'
    identifier = 'test-double-not-real-model'

    def load(self, settings):
        pass

    def close(self):
        self.ready = False


def settings(**kwargs):
    return Settings(_env_file=None, ai_service_api_key=KEY, **kwargs)


def test_health_requires_internal_key_and_never_returns_it():
    with TestClient(create_app(settings(), FakeRuntime())) as client:
        for headers in ({}, {'X-API-Key': 'wrong'}):
            response = client.get('/health', headers=headers)
            assert response.status_code == 401
            assert response.json()['error']['code'] == 'unauthorized'
        response = client.get('/health', headers={'X-API-Key': KEY})
        assert response.status_code == 200
        assert response.json()['model_name'] == 'buffalo_m'
        assert response.json()['execution_provider'] == 'CPUExecutionProvider'
        assert KEY not in response.text
        assert response.headers['cache-control'] == 'no-store'
        assert 'access-control-allow-origin' not in response.headers


def test_missing_weights_are_unavailable_not_fake_success(tmp_path):
    with TestClient(create_app(settings(face_model_root=tmp_path))) as client:
        response = client.get('/health', headers={'X-API-Key': KEY})
        assert response.status_code == 503
        assert response.json()['code'] == 'model_files_missing'
        assert response.json()['model_identifier'] is None


def test_rate_limit_and_no_public_docs():
    with TestClient(create_app(settings(ai_health_requests_per_minute=2), FakeRuntime())) as client:
        headers = {'X-API-Key': KEY}
        assert client.get('/health', headers=headers).status_code == 200
        assert client.get('/docs', headers=headers).status_code == 404
        response = client.get('/health', headers=headers)
        assert response.status_code == 429
        assert response.headers['retry-after'] == '60'


@pytest.mark.parametrize('values', [
    {'ai_service_api_key': 'short'},
    {'ai_service_api_key': KEY, 'face_model_name': 'buffalo_l'},
    {'ai_service_api_key': KEY, 'ai_health_requests_per_minute': 0},
])
def test_configuration_fails_closed(values):
    with pytest.raises(ValidationError):
        Settings(_env_file=None, **values)


def test_modified_model_is_rejected_before_inference(tmp_path):
    folder = tmp_path / 'models' / 'buffalo_m'
    folder.mkdir(parents=True)
    for name in ('det_2.5g.onnx', 'w600k_r50.onnx'):
        (folder / name).write_bytes(b'not an ONNX model')
    (folder / 'facetrack-source.json').write_text('{}')
    model = ModelRuntime()
    model.load(settings(face_model_root=tmp_path))
    assert model.ready is False
    assert model.code == 'model_load_failed'


def test_shutdown_releases_runtime():
    runtime = FakeRuntime()
    with TestClient(create_app(settings(), runtime)):
        assert runtime.ready
    assert runtime.ready is False
