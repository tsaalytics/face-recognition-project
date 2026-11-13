"""Create a local service key without printing it or replacing existing configuration."""
from pathlib import Path
import secrets

if __name__ == '__main__':
    destination = Path(__file__).resolve().parents[1] / '.env'
    with destination.open('x', encoding='utf-8') as target:
        target.write(f'AI_SERVICE_API_KEY={secrets.token_urlsafe(48)}\nFACE_MODEL_NAME=buffalo_m\n')
    print('Created private ai-service/.env. Key was not printed.')
