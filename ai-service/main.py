"""Authenticated internal health and single-frame embedding extraction."""
import anyio
from collections import deque
from contextlib import asynccontextmanager
import hmac
from threading import Lock
from time import monotonic

from fastapi import FastAPI, Request
from fastapi.responses import JSONResponse
from starlette.concurrency import run_in_threadpool
from starlette.requests import ClientDisconnect

from config import Settings
from model_runtime import ModelRuntime
from face_engine import FaceEngine, FaceError
from schemas import HealthResponse, EmbeddingResponse


def create_app(settings: Settings | None = None, runtime: ModelRuntime | None = None) -> FastAPI:
    @asynccontextmanager
    async def lifespan(app: FastAPI):
        app.state.settings = settings if settings is not None else Settings()
        app.state.runtime = runtime if runtime is not None else ModelRuntime()
        app.state.runtime.load(app.state.settings)
        yield
        app.state.runtime.close()

    app = FastAPI(title='FaceTrack AI Internal Service', lifespan=lifespan,
                  docs_url=None, redoc_url=None, openapi_url=None)
    requests = deque()
    extract_requests = deque()
    lock = Lock()
    inference_lock = Lock()

    @app.middleware('http')
    async def internal_access(request: Request, call_next):
        # No CORS; key is shared by Laravel and this service only.
        supplied = request.headers.get('X-API-Key', '')
        expected = app.state.settings.ai_service_api_key.get_secret_value()
        if not hmac.compare_digest(supplied.encode(), expected.encode()):
            response = JSONResponse({'error': {'code': 'unauthorized', 'message': 'Invalid service credentials.'}}, status_code=401)
        else:
            with lock:
                now = monotonic()
                extracting = request.url.path == '/face/extract-embedding'
                bucket = extract_requests if extracting else requests
                limit = app.state.settings.ai_extract_requests_per_minute if extracting else app.state.settings.ai_health_requests_per_minute
                while bucket and bucket[0] <= now - 60:
                    bucket.popleft()
                limited = len(bucket) >= limit
                if not limited:
                    bucket.append(now)
            if limited:
                response = JSONResponse({'error': {'code': 'rate_limited', 'message': 'Request limit reached.'}}, status_code=429, headers={'Retry-After': '60'})
            else:
                response = await call_next(request)
        response.headers['Cache-Control'] = 'no-store'
        response.headers['X-Content-Type-Options'] = 'nosniff'
        return response

    @app.get('/health', response_model=HealthResponse)
    def health():
        model = app.state.runtime
        body = HealthResponse(status='ready' if model.ready else 'unavailable',
                              code=model.code, model_identifier=model.identifier)
        return JSONResponse(body.model_dump(), status_code=200 if model.ready else 503)

    @app.exception_handler(FaceError)
    async def face_error_handler(request: Request, error: FaceError):
        return JSONResponse({'error': {'code': error.code, 'message': error.message}}, status_code=error.status)

    @app.post('/face/extract-embedding', response_model=EmbeddingResponse)
    async def extract_embedding(request: Request):
        config = app.state.settings
        content_type = request.headers.get('content-type', '').split(';', 1)[0].strip().lower()
        if content_type not in {'image/jpeg', 'image/png', 'image/webp'}:
            raise FaceError('unsupported_image_type', 'Gunakan gambar JPEG, PNG, atau WebP.', 415)
        if request.headers.get('content-encoding', 'identity').lower() != 'identity':
            raise FaceError('unsupported_content_encoding', 'Body terkompresi tidak didukung.', 415)
        length = request.headers.get('content-length')
        if length is not None:
            if not length.isascii() or not length.isdecimal():
                raise FaceError('invalid_content_length', 'Panjang request tidak valid.', 400)
            if len(length) > 20 or int(length) > config.ai_max_image_bytes:
                raise FaceError('image_too_large', 'Ukuran gambar melebihi batas.', 413)
        payload = bytearray()
        try:
            with anyio.fail_after(config.ai_body_timeout):
                async for chunk in request.stream():
                    if len(payload) + len(chunk) > config.ai_max_image_bytes:
                        raise FaceError('image_too_large', 'Ukuran gambar melebihi batas.', 413)
                    payload.extend(chunk)
        except TimeoutError:
            raise FaceError('request_timeout', 'Pengiriman gambar melewati batas waktu.', 408) from None
        except ClientDisconnect:
            raise FaceError('incomplete_request', 'Pengiriman gambar terputus.', 400) from None

        def infer():
            if not inference_lock.acquire(blocking=False):
                raise FaceError('service_busy', 'Sistem sedang sibuk. Silakan coba kembali.', 503)
            try:
                result = FaceEngine(config, app.state.runtime).extract(payload, content_type)
                return EmbeddingResponse.model_validate(result)
            except FaceError:
                raise
            except Exception:
                raise FaceError('inference_failed', 'Sistem sedang mengalami gangguan. Silakan coba kembali.', 503) from None
            finally:
                inference_lock.release()

        try:
            return await run_in_threadpool(infer)
        finally:
            payload.clear()

    return app


app = create_app()
