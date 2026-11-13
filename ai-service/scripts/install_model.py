"""Install the exact official buffalo_m archive; never substitute another pack."""
import json
from pathlib import Path
import shutil
import sys
import tempfile
import urllib.request
import zipfile

sys.path.insert(0, str(Path(__file__).resolve().parents[1]))
from model_runtime import ARCHIVE_SHA256, MODEL_FILES, MODEL_URL, file_digest


def install(root: Path) -> None:
    destination = root.resolve() / 'models' / 'buffalo_m'
    if destination.exists():
        raise SystemExit('Model directory already exists; inspect it before replacing manually.')
    destination.parent.mkdir(parents=True, exist_ok=True)
    # Stage on the same disk; TemporaryDirectory removes archive/partial data on failure.
    with tempfile.TemporaryDirectory(dir=destination.parent, prefix='.buffalo-m-') as temp:
        stage = Path(temp)
        archive = stage / 'buffalo_m.zip'
        print('Downloading official buffalo_m archive...', flush=True)
        with urllib.request.urlopen(MODEL_URL, timeout=60) as source, archive.open('wb') as target:
            total = 0
            while chunk := source.read(1024 * 1024):
                total += len(chunk)
                if total > 400 * 1024 * 1024:
                    raise ValueError('Archive exceeds expected size limit')
                target.write(chunk)
        if file_digest(archive) != ARCHIVE_SHA256:
            raise ValueError('Official archive SHA-256 mismatch; installation aborted')
        output = stage / 'pack'
        output.mkdir()
        with zipfile.ZipFile(archive) as bundle:
            # Only inference weights required by this prototype are retained.
            for name in MODEL_FILES:
                candidates = [item for item in bundle.infolist() if Path(item.filename).name == name]
                if len(candidates) != 1 or candidates[0].file_size > 300 * 1024 * 1024:
                    raise ValueError('Missing, duplicate or oversized model file')
                with bundle.open(candidates[0]) as source, (output / name).open('wb') as target:
                    shutil.copyfileobj(source, target)
        metadata = {'model_name': 'buffalo_m', 'source': MODEL_URL,
                    'archive_sha256': ARCHIVE_SHA256,
                    'files': {name: file_digest(output / name) for name in MODEL_FILES}}
        (output / 'facetrack-source.json').write_text(json.dumps(metadata, indent=2))
        output.rename(destination)
    print('Verified buffalo_m detection and recognition weights installed.', flush=True)


if __name__ == '__main__':
    import argparse
    parser = argparse.ArgumentParser()
    parser.add_argument('--root', type=Path, default=Path(__file__).resolve().parents[1] / '.models')
    install(parser.parse_args().root)
