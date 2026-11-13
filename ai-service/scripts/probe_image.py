"""Probe a consenting participant image already supplied locally; never print the vector."""
import argparse
from pathlib import Path
import sys

sys.path.insert(0, str(Path(__file__).resolve().parents[1]))
import httpx
import numpy as np
from PIL import Image
from config import Settings


if __name__ == '__main__':
    parser = argparse.ArgumentParser(description='Use only images with participant consent.')
    parser.add_argument('image', type=Path)
    parser.add_argument('--consent-confirmed', action='store_true', required=True)
    args = parser.parse_args()
    settings = Settings()
    try:
        if args.image.stat().st_size > settings.ai_max_image_bytes:
            raise ValueError('Image exceeds maximum size')
        with Image.open(args.image) as image:
            mime = {'JPEG': 'image/jpeg', 'PNG': 'image/png', 'WEBP': 'image/webp'}.get(image.format)
        if mime is None:
            raise ValueError('Unsupported image')
        response = httpx.post('http://127.0.0.1:8001/face/extract-embedding', content=args.image.read_bytes(),
                              headers={'X-API-Key': settings.ai_service_api_key.get_secret_value(), 'Content-Type': mime},
                              timeout=30, trust_env=False)
        body = response.json()
        if response.status_code == 200:
            print(f"HTTP 200: model={body['model_name']}, dimension={body['embedding_dimension']}, norm={np.linalg.norm(body['embedding']):.6f}")
        else:
            print(f"HTTP {response.status_code}: {body.get('error', {}).get('code', 'unknown_error')}")
        sys.exit(0 if response.status_code == 200 else 1)
    except Exception:
        print('Probe failed. Check the local service and input image. No payload was logged.')
        sys.exit(1)
