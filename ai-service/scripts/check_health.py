"""Real network health probe; reads secret server-side and never prints it."""
from pathlib import Path
import sys

sys.path.insert(0, str(Path(__file__).resolve().parents[1]))
import httpx
from config import Settings

if __name__ == '__main__':
    try:
        settings = Settings()
        response = httpx.get('http://127.0.0.1:8001/health',
                             headers={'X-API-Key': settings.ai_service_api_key.get_secret_value()},
                             timeout=10, trust_env=False)
        print(f'HTTP {response.status_code}: {response.text}')
        sys.exit(0 if response.status_code == 200 else 1)
    except Exception:
        print('Health probe failed. Check the local service and server configuration.')
        sys.exit(1)
