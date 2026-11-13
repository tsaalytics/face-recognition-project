import hashlib
import io
import json
import zipfile

import pytest

from scripts import install_model


def archive_bytes():
    stream = io.BytesIO()
    with zipfile.ZipFile(stream, 'w') as archive:
        for name in install_model.MODEL_FILES:
            archive.writestr(name, b'synthetic test weights')
        archive.writestr('../outside.txt', b'untrusted path')
    return stream.getvalue()


def test_install_checks_archive_and_only_retains_required_files(tmp_path, monkeypatch):
    payload = archive_bytes()
    monkeypatch.setattr(install_model.urllib.request, 'urlopen', lambda *a, **k: io.BytesIO(payload))
    monkeypatch.setattr(install_model, 'ARCHIVE_SHA256', hashlib.sha256(payload).hexdigest())
    install_model.install(tmp_path)
    folder = tmp_path / 'models' / 'buffalo_m'
    assert {p.name for p in folder.iterdir()} == set(install_model.MODEL_FILES) | {'facetrack-source.json'}
    metadata = json.loads((folder / 'facetrack-source.json').read_text())
    assert metadata['model_name'] == 'buffalo_m'
    assert not (tmp_path / 'outside.txt').exists()
    assert not list(tmp_path.rglob('*.zip'))
    with pytest.raises(SystemExit):
        install_model.install(tmp_path)


def test_bad_digest_fails_and_cleans_partial_download(tmp_path, monkeypatch):
    monkeypatch.setattr(install_model.urllib.request, 'urlopen', lambda *a, **k: io.BytesIO(b'wrong archive'))
    with pytest.raises(ValueError, match='SHA-256 mismatch'):
        install_model.install(tmp_path)
    assert not (tmp_path / 'models' / 'buffalo_m').exists()
    assert list((tmp_path / 'models').iterdir()) == []
