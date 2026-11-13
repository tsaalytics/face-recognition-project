<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;

class FaceExtractionClient
{
    public function extract(string $image, string $mime, float $remainingSeconds = 25): array
    {
        $url = rtrim((string) config('face.service_url'), '/');
        $key = (string) config('face.api_key');
        if (strlen($key) < 32 || config('face.model_name') !== 'buffalo_m' || ! preg_match('#^https?://[^\s]+$#', $url)) {
            $this->fail('Layanan AI belum dikonfigurasi. Hubungi Admin.');
        }
        try {
            // No automatic retries or exception logging: request/response contain biometrics.
            $response = Http::withHeaders(['X-API-Key' => $key])->acceptJson()
                ->connectTimeout(min(3, $remainingSeconds))->timeout(min($remainingSeconds, max(1, min(30, (int) config('face.request_timeout')))))
                ->withOptions(['allow_redirects' => false])->withBody($image, $mime)
                ->post($url.'/face/extract-embedding');
        } catch (\Throwable) {
            $this->fail('Layanan AI tidak dapat dihubungi atau melewati batas waktu. Silakan coba kembali.');
        }
        if (! $response->successful()) {
            $messages = [
                'no_face_detected' => 'Wajah tidak terdeteksi. Posisikan wajah di depan kamera.',
                'multiple_faces_detected' => 'Terdeteksi lebih dari satu wajah. Pastikan hanya satu orang.',
                'face_too_small' => 'Wajah terlalu kecil. Mendekat ke kamera.',
                'face_too_blurry' => 'Wajah terlalu blur. Tahan posisi dan coba kembali.',
                'poor_lighting' => 'Pencahayaan wajah tidak memadai.',
                'face_out_of_frame' => 'Pastikan seluruh wajah berada di dalam bingkai.',
                'invalid_landmarks' => 'Hadapkan wajah ke kamera dan coba kembali.',
                'low_detection_confidence' => 'Wajah kurang jelas. Silakan coba kembali.',
            ];
            $code = $response->json('error.code');
            $this->fail($messages[is_string($code) ? $code : ''] ?? 'Layanan AI sedang tidak tersedia. Silakan coba kembali.');
        }
        $data = $response->json();
        if (! is_array($data) || ($data['status'] ?? null) !== 'ok' || ($data['model_name'] ?? null) !== 'buffalo_m'
            || ($data['embedding_dimension'] ?? null) !== 512
            || ! preg_match('/^buffalo_m:sha256:[a-f0-9]{64}$/D', (string) ($data['model_identifier'] ?? ''))
            || ! is_array($data['embedding'] ?? null) || ! array_is_list($data['embedding']) || count($data['embedding']) !== 512) {
            $this->fail('Respons layanan AI tidak valid.');
        }
        $sum = 0.0;
        foreach ($data['embedding'] as $value) {
            if ((! is_int($value) && ! is_float($value)) || ! is_finite((float) $value) || abs($value) > 1) {
                $this->fail('Respons embedding AI tidak valid.');
            }
            $sum += $value * $value;
        }
        if (abs(sqrt($sum) - 1) > 0.001) {
            $this->fail('Embedding AI belum ternormalisasi.');
        }
        // Copy only approved numeric metadata; never persist arbitrary API response fields.
        $quality = [];
        foreach (['detection_score', 'face_width', 'face_height', 'blur_variance', 'brightness'] as $field) {
            $value = $data['quality'][$field] ?? null;
            if ((! is_int($value) && ! is_float($value)) || ! is_finite((float) $value) || $value < 0) {
                $this->fail('Metadata kualitas AI tidak valid.');
            }
            $quality[$field] = $value;
        }

        return ['embedding' => $data['embedding'], 'model_identifier' => $data['model_identifier'], 'quality' => $quality];
    }

    private function fail(string $message): never
    {
        throw ValidationException::withMessages(['samples' => $message]);
    }
}
