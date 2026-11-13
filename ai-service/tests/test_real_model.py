"""Opt-in: actual weights, synthetic inputs; no participant photos or identities."""
import os

from fastapi.testclient import TestClient
import numpy as np
import cv2
import pytest

from config import Settings
from main import create_app
from face_engine import normalize_embedding


@pytest.mark.skipif(os.getenv('FACE_TEST_REAL_MODEL') != '1', reason='Opt-in real buffalo_m smoke')
def test_real_buffalo_m_cpu_load_and_blank_frame():
    settings = Settings()
    with TestClient(create_app(settings)) as client:
        response = client.get('/health', headers={'X-API-Key': settings.ai_service_api_key.get_secret_value()})
        assert response.status_code == 200, response.json()
        assert response.json()['model_identifier'].startswith('buffalo_m:sha256:')
        runtime = client.app.state.runtime
        assert runtime.analysis.get(np.zeros((640, 640, 3), dtype=np.uint8)) == []
        vector = runtime.analysis.models['recognition'].get_feat(np.zeros((112, 112, 3), dtype=np.uint8))
        assert vector.shape == (1, 512)
        assert np.isfinite(vector).all()
        # Exercise the actual HTTP extraction route and real detection with a blank frame.
        png = cv2.imencode('.png', np.zeros((640, 640, 3), dtype=np.uint8))[1].tobytes()
        response = client.post('/face/extract-embedding', content=png,
                               headers={'X-API-Key': settings.ai_service_api_key.get_secret_value(), 'Content-Type': 'image/png'})
        assert response.status_code == 422
        assert response.json()['error']['code'] == 'no_face_detected'
        # Actual alignment + recognition on synthetic landmarks; not a real detected face.
        from insightface.app.common import Face
        face = Face(kps=np.array([[38, 52], [74, 52], [56, 72], [42, 92], [70, 92]], dtype=np.float32))
        raw = runtime.analysis.models['recognition'].get(np.zeros((112, 112, 3), dtype=np.uint8), face)
        assert np.linalg.norm(normalize_embedding(raw)) == pytest.approx(1)
