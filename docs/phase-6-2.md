# Phase 6.2 - Face embedding engine

Tanggal: 13 November 2026 (Asia/Jakarta).

## Implementasi

- `POST /face/extract-embedding` adalah endpoint internal dengan header `X-API-Key`.
  Body berupa byte gambar, Content-Type JPEG/PNG/WebP; bukan JSON, base64 atau multipart.
- Body dibaca melalui stream di memory: maksimal 5 MiB, timeout 10 detik, rate limit
  30 request/menit per service credential/proses. Ukuran tetap diperiksa meskipun
  Content-Length tidak ada atau tidak dapat dipercaya. Content-Encoding ditolak.
- Header gambar diperiksa sebelum decode OpenCV: format cocok dengan MIME, bukan
  animasi, sisi maksimal 4096, maksimal 12 juta pixel, sisi minimal 32 pixel.
  EXIF orientation diabaikan; kirim frame canvas yang sudah berorientasi benar.
- Detector buffalo_m memeriksa seluruh wajah (`max_num=0`), tanpa memilih satu wajah
  secara diam-diam. Nol wajah dan beberapa wajah ditolak sebelum recognition.
- Crop harus berada penuh di frame, minimal 80 pixel per sisi; detection score
  minimal 0,6. Lima landmark finite, berbeda dan tidak kolinear wajib tersedia.
- Pemeriksaan kualitas menggunakan crop grayscale pada 112x112: Laplacian variance
  minimal 50 dan brightness rata-rata 40-215. Ini heuristik awal configurable,
  **belum dikalibrasi atau dinyatakan optimal**; bukan estimasi pose/liveness.
- InsightFace recognition.get melakukan alignment lima landmark dan ekstraksi
  embedding. Hasil harus finite, tepat 512 dimensi dan magnitude tidak nol,
  kemudian dinormalisasi L2. Model identifier sama dengan runtime Phase 6.1.
- Inference berjalan di worker thread agar event loop tetap responsif. Satu inference
  aktif per proses; request paralel mendapat `503 service_busy`, tanpa antrean inference
  tak terbatas. Lock dipulihkan setelah sukses/gagal.
- Tidak ada pemanggilan penyimpanan file/gambar/embedding, tidak memakai UploadFile,
  dan tidak ada temporary upload spool. Buffer request dibersihkan setelah inference.
  Ini bukan klaim secure memory erasure; Python/OpenCV dapat membuat salinan memory.

Endpoint mengembalikan embedding **hanya kepada Laravel/server berotorisasi**, tidak
langsung ke browser. Laravel nanti harus menyaring response dan tidak meneruskan
raw embedding ke UI. Tidak ada endpoint AI yang dibuka untuk browser pada tahap ini.

## Kontrak respons

Sukses HTTP 200: `status=ok`, `model_name=buffalo_m`, `model_identifier`,
`embedding_dimension=512`, `embedding` array finite dengan norm 1, dan `quality`
(detection_score, face_width, face_height, blur_variance, brightness).

Gagal: `{"error":{"code":"...","message":"..."}}`, tanpa vektor atau identitas.

| HTTP | Code utama |
| --- | --- |
| 401 | unauthorized |
| 408 | request_timeout |
| 413 | image_too_large |
| 415 | unsupported_image_type, unsupported_content_encoding |
| 422 | invalid_image, invalid_image_dimensions, no_face_detected, multiple_faces_detected, face_too_small, face_out_of_frame, low_detection_confidence, invalid_landmarks, poor_lighting, face_too_blurry |
| 429 | rate_limited |
| 503 | service_unavailable, service_busy, invalid_detection, invalid_embedding, inference_failed |

## Matching Laravel

`FaceEmbeddingMatcher` menyediakan cosine similarity dan nearest match pada vektor
512 dimensi. Threshold harus diberikan eksplisit; null menghasilkan Unknown (`null`).
Skor di bawah threshold, gallery kosong, atau kandidat teratas seri juga menghasilkan
Unknown tanpa mengembalikan ID kandidat. Threshold tidak finite/di luar (0,1] dan
vektor invalid ditolak. Nilai threshold pada tes hanyalah angka sintetis pengujian.

Utility ini tidak membaca database dan belum dihubungkan ke attendance. Caller tahap
6.3/6.4 harus memasok gallery karyawan aktif/enrolled saja, memastikan model identifier
dan dimensi cocok, mendekripsi hanya di server memory, serta melakukan transaksi dan
otorisasi. Perbandingan multi-sampel per karyawan/consistency enrollment diselesaikan
pada tahap 6.3. Jangan mencampurkan model/versi berbeda dalam satu gallery.

## Menjalankan

Environment/model Phase 6.1 tetap dipakai; tidak perlu migrasi, seed, atau unduhan model
ulang. Restart service yang masih menjalankan kode 6.1 dengan perintah berikut:

```powershell
ai-service/.venv/Scripts/python.exe -m uvicorn main:app --app-dir ai-service --host 127.0.0.1 --port 8001 --workers 1 --no-access-log
```

Untuk mencoba satu foto peserta yang **sudah memberikan persetujuan**, gunakan path
ke input lokal yang sudah dimiliki; script tidak menyalin foto dan tidak mencetak vektor:

```powershell
ai-service/.venv/Scripts/python.exe ai-service/scripts/probe_image.py C:/private/consented-sample.jpg --consent-confirmed
```

Jangan letakkan foto peserta di repo/OneDrive proyek. Menyediakan path tidak menghapus
input asal; pengguna tetap bertanggung jawab atas retensi input evaluasinya.
Script belum diuji dengan foto peserta nyata karena belum tersedia input berizin.

Pengaturan tersedia pada `ai-service/.env.example`. Menambah variabel ke `.env`
bersifat opsional; defaults di Settings berlaku bila variabel tidak ada.
Untuk server publik gunakan jaringan privat/TLS dan batas body/timeout reverse proxy;
limiter internal per proses bukan pertahanan distributed DoS. Timeout body tidak
membatalkan kernel ONNX yang sudah berjalan; timeout HTTP client Laravel harus
dirancang pada integrasi berikutnya tanpa retry inference tak terbatas.

## Pengujian aktual

```powershell
$env:FACE_TEST_REAL_MODEL='1'
ai-service/.venv/Scripts/python.exe -m pytest -c ai-service/pytest.ini ai-service/tests
Remove-Item Env:FACE_TEST_REAL_MODEL
php artisan test --filter FaceEmbeddingMatcherTest
```

- Python: **43 passed**, termasuk test sebelumnya dan model buffalo_m asli.
- Mock: satu/banyak/tanpa wajah, kualitas, normalisasi, output invalid, MIME/format,
  ukuran header/stream, dimensi sebelum decode, animasi, timeout, paralel/busy/recovery,
  error service, autentikasi/rate limit, dan tidak ada file output pada direktori tes.
- Model asli: CPU load; frame kosong melalui endpoint menghasilkan no_face_detected;
  alignment pada landmark sintetis + recognition menghasilkan 512 dimensi normalized.
  Ini **bukan deteksi satu wajah nyata**, evaluasi identitas, atau bukti anti-spoofing.
- Laravel matcher: **4 passed, 19 assertions**. Pint untuk kedua file PHP lulus.
- Satu warning deprecation transport httpx pada Starlette TestClient masih ada;
  tidak ada kegagalan tes. Tidak ada dependency baru yang diunduh.
- Belum diuji: sukses single-face/multiple-face dengan foto peserta asli, accuracy,
  FAR/FRR, kalibrasi threshold/kualitas, latency benchmark dan browser sensor.

## File

- Baru: `ai-service/face_engine.py`, `tests/test_extraction.py`, `scripts/probe_image.py`.
- Diubah: `ai-service/{main,config,schemas}.py`, `.env.example`, `requirements.txt`,
  dan `tests/test_real_model.py`.
- Baru: `app/Services/FaceEmbeddingMatcher.php`, `tests/Unit/FaceEmbeddingMatcherTest.php`.
- Dokumentasi: dokumen ini, README dan `docs/ai_architecture.md`.

Binding Laravel tetap disabled; enrollment/attendance web belum aktif. Tidak ada
migrasi database, penyimpanan biometrik baru, commit atau push. Berikutnya Phase 6.3.
