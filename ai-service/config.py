"""Server-only settings. Never expose AI_SERVICE_API_KEY to the browser."""
from pathlib import Path
from typing import Literal

from pydantic import Field, SecretStr, field_validator
from pydantic_settings import BaseSettings, SettingsConfigDict

BASE_DIR = Path(__file__).resolve().parent


class Settings(BaseSettings):
    model_config = SettingsConfigDict(
        env_file=BASE_DIR / '.env', env_file_encoding='utf-8', extra='ignore',
        hide_input_in_errors=True,
    )

    ai_service_api_key: SecretStr
    face_model_name: Literal['buffalo_m'] = 'buffalo_m'
    face_model_root: Path = BASE_DIR / '.models'
    ai_health_requests_per_minute: int = Field(default=60, ge=1, le=600)
    ai_extract_requests_per_minute: int = Field(default=30, ge=1, le=300)
    ai_max_image_bytes: int = Field(default=5 * 1024 * 1024, ge=1024, le=10 * 1024 * 1024)
    ai_max_image_side: int = Field(default=4096, ge=640, le=8192)
    ai_max_image_pixels: int = Field(default=12000000, ge=409600, le=20000000)
    ai_body_timeout: float = Field(default=10, gt=0, le=60)
    face_min_size: int = Field(default=80, ge=32, le=640)
    face_min_detection_score: float = Field(default=0.6, ge=0.5, le=1)
    face_min_blur_variance: float = Field(default=50, ge=0, le=1000)
    face_min_brightness: float = Field(default=40, ge=0, le=127)
    face_max_brightness: float = Field(default=215, ge=128, le=255)

    @field_validator('ai_service_api_key')
    @classmethod
    def validate_key(cls, value: SecretStr) -> SecretStr:
        if len(value.get_secret_value()) < 32:
            raise ValueError('AI_SERVICE_API_KEY must contain at least 32 characters')
        return value

