from typing import Literal

from pydantic import BaseModel, Field


class HealthResponse(BaseModel):
    status: Literal['ready', 'unavailable']
    code: str
    model_name: Literal['buffalo_m'] = 'buffalo_m'
    model_identifier: str | None = None
    execution_provider: Literal['CPUExecutionProvider'] = 'CPUExecutionProvider'
    embedding_dimension: int = 512


class QualityResponse(BaseModel):
    detection_score: float
    face_width: float
    face_height: float
    blur_variance: float
    brightness: float


class EmbeddingResponse(BaseModel):
    status: Literal['ok'] = 'ok'
    model_name: Literal['buffalo_m'] = 'buffalo_m'
    model_identifier: str
    embedding_dimension: Literal[512] = 512
    embedding: list[float] = Field(min_length=512, max_length=512)
    quality: QualityResponse

