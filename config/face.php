<?php

return [
    // Server-only configuration. Enrollment uses the extraction client; attendance stays disabled.
    'service_url' => env('AI_SERVICE_URL', 'http://127.0.0.1:8001'),
    'api_key' => env('AI_SERVICE_API_KEY'),
    'request_timeout' => (int) env('AI_REQUEST_TIMEOUT', 15),
    'model_name' => env('FACE_MODEL_NAME', 'buffalo_m'),
    // Null until calibration: recognition must fail closed without a chosen threshold.
    'similarity_threshold' => env('FACE_SIMILARITY_THRESHOLD'),
    // Provisional enrollment heuristics, not a calibrated attendance threshold.
    'enrollment_consistency_threshold' => (float) env('FACE_ENROLLMENT_CONSISTENCY_THRESHOLD', 0.65),
    'enrollment_duplicate_threshold' => (float) env('FACE_ENROLLMENT_DUPLICATE_THRESHOLD', 0.9999),
];
