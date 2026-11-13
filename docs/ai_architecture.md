# Phase 6.1 — Audit dan setup AI service

Catatan terbaru: [HR enrollment Phase 6.3](phase-6-3.md) sudah diimplementasikan;
attendance recognition belum aktif. Engine ekstraksi Phase 6.2 sudah tersedia. Lihat
[Phase 6.2](phase-6-2.md) untuk kontrak endpoint, tes dan batas verifikasi terkini.
Bagian 6.1 di bawah adalah catatan historis setup.

Tanggal: 13 November 2026, Asia/Jakarta. Scope tahap ini: runtime Python CPU,
provisioning buffalo_m, autentikasi internal, health/readiness dan tes setup.
Enrollment dan attendance recognition di web belum diaktifkan.

## Hasil audit Laravel

- `FaceRecognitionService` dan `DisabledFaceRecognitionService` adalah placeholder;
  `enroll`/`verify` menerima path privat dan bertipe `never`. Kontrak harus direvisi
  untuk byte in-memory dan DTO saat integrasi; belum diubah pada tahap ini.
- Employee memakai PK internal `id`; `business_id` adalah ID bisnis, bukan FK.
  `user_id` opsional/unik; status `is_active` dan `enrollment_status` sudah ada.
- Belum ada tabel embedding atau audit biometrik. Tahap 6.3 menambah tabel melalui
  migrasi aditif, encrypted cast, FK employee dan audit metadata tanpa vektor/foto.
- Attendance unik pada employee/date/session. Migrasi refinement telah mengubah
  `source` menjadi VARCHAR(40), sehingga `face_recognition` dapat ditampung.
- ApprovalService mengunci Employee lalu request/attendance. Pencatatan AI nanti
  harus memakai urutan lock yang sama, memeriksa leave dan tidak menimpa koreksi HR.
- Jadwal existing: regular 08:00, grace 0, Asia/Jakarta. Ini konfigurasi existing,
  bukan hasil kalibrasi; fase attendance perlu keputusan jadwal kiosk yang eksplisit.
- Bukti koreksi existing disimpan privat sesuai workflow lama. Larangan menyimpan
  foto enrollment/scan AI diterapkan pada alur AI baru; bukti koreksi tidak dialihkan
  menjadi sumber enrollment dan tidak dihapus oleh tahap ini.
- Route HR enrollment saat ini read-only. Role/aktivasi/invitation/approval tidak diubah.

## Batas komponen

Browser camera → Laravel (auth, CSRF, consent, ukuran/MIME, byte sementara)
→ Python internal (deteksi/alignment/embedding) → Laravel (matching terenkripsi
yang didekripsi di memory, aturan attendance, transaksi) → browser (hasil saja).

Laravel tetap bertanggung jawab atas identitas dan business rules. Python tidak
mempunyai koneksi database, profil karyawan, penyimpanan gambar, atau matching gallery.
Nearest-match cosine dan threshold yang dikalibrasi akan diimplementasikan di Laravel.
Pencarian linear untuk MVP membutuhkan O(N × 512) dan dekripsi gallery per scan;
evaluasi ulang desain ketika jumlah karyawan/konkurensi bertambah.

## Runtime tahap 6.1

`main.py` memakai lifespan untuk memuat model sekali per proses. `/health` butuh
header `X-API-Key`, mengembalikan 200 hanya jika dua model ONNX berhasil dimuat,
provider aktual CPU, serta synthetic detection/recognition warm-up berhasil.
Model belum tersedia/integritas gagal/load gagal menghasilkan 503, tanpa fallback.
Kunci kosong atau kurang dari 32 karakter menggagalkan startup.

Model dipanggil eksplisit dengan `FaceAnalysis(name="buffalo_m")`, hanya task
detection dan recognition. Installer mengambil arsip resmi berikut:

- [InsightFace model zoo](https://github.com/deepinsight/insightface/blob/master/python-package/docs/model_zoo.md)
- [Release resmi buffalo_m](https://github.com/deepinsight/insightface/releases/download/model-zoo/buffalo_m.zip)
- SHA-256 arsip: `d98264bd8f2dc75cbc2ddce2a14e636e02bb857b3051c234b737bf3b614edca9`

Installer memverifikasi digest arsip, menyimpan hanya `det_2.5g.onnx` dan
`w600k_r50.onnx`, serta metadata provenance/hash lokal. Model identifier mencakup
hash kedua file; startup memeriksa ulang integritas sebelum memuat. Folder yang
sudah ada tidak ditimpa otomatis. Tidak ada download saat melayani request.

Health dibatasi 60 request/menit per proses untuk service credential. Gunakan satu
worker lokal; deployment multi-worker perlu limiter bersama/reverse proxy.
Docs/OpenAPI dan CORS dimatikan, respons no-store, bind hanya 127.0.0.1.
Gunakan jaringan privat + TLS dan pembatasan koneksi pada deployment terpisah.
Jangan mengaktifkan logging body/headers atau middleware pelacak payload.

Belum ada endpoint upload di tahap ini, sehingga tidak ada temporary upload file.
Tahap 6.2 harus membatasi body sebelum decoding, memvalidasi format/dimensi dan
memproses byte di memory. Jika memakai multipart UploadFile, spool dapat jatuh ke
temporary disk; pilih raw body terbatas untuk AI atau dokumentasikan cleanup eksplisit.

Konfigurasi Laravel disiapkan pada `config/face.php` dan `.env.example`, tetapi
binding tetap disabled. `FACE_SIMILARITY_THRESHOLD` sengaja kosong sampai evaluasi;
jangan menganggap angka threshold umum sebagai optimal.

## Menjalankan pada Windows

Dari root proyek (Python 3.10 yang diuji):

```powershell
py -3.10 -m venv ai-service/.venv
ai-service/.venv/Scripts/python.exe -m pip install -r ai-service/requirements-lock.txt
ai-service/.venv/Scripts/python.exe ai-service/scripts/init_env.py
ai-service/.venv/Scripts/python.exe ai-service/scripts/install_model.py
ai-service/.venv/Scripts/python.exe -m uvicorn main:app --app-dir ai-service --host 127.0.0.1 --port 8001 --workers 1 --no-access-log
```

Environment dan model pada workspace ini sudah disiapkan jika hasil verifikasi di
bawah menyatakan berhasil. Jangan jalankan init_env lagi bila `.env` sudah ada.
Jika ingin menyimpan model di luar OneDrive, set `FACE_MODEL_ROOT` ke path absolut
dan gunakan `install_model.py --root <path>` yang sama. Folder biometrik di fase
berikutnya juga harus dikecualikan dari cloud sync dan Git.

Di terminal lain:

```powershell
ai-service/.venv/Scripts/python.exe ai-service/scripts/check_health.py
ai-service/.venv/Scripts/python.exe -m pytest -c ai-service/pytest.ini ai-service/tests
ai-service/.venv/Scripts/python.exe -m pip check
```

Untuk smoke test model asli (blank frame sintetis, bukan evaluasi identitas):

```powershell
$env:FACE_TEST_REAL_MODEL='1'
ai-service/.venv/Scripts/python.exe -m pytest -c ai-service/pytest.ini ai-service/tests/test_real_model.py
Remove-Item Env:FACE_TEST_REAL_MODEL
```

Salin key secara privat ke `.env` Laravel ketika client integrasi tahap berikutnya
diaktifkan; jangan gunakan prefix VITE atau masukkan key ke browser. Tidak ada
migrasi/seed yang diperlukan untuk 6.1. Tidak ada commit/push otomatis.

## Verifikasi aktual

- CPython 3.10.1, InsightFace 2.1, ONNX Runtime 1.23.2 CPU, OpenCV 5.0.0,
  NumPy 2.2.6 dan FastAPI 0.162.5 terpasang dalam `.venv` terpisah.
  `requirements.txt` memuat dependency langsung; `requirements-lock.txt` adalah
  hasil freeze seluruh dependency environment yang diuji.
- Arsip resmi 275.951.529 byte berhasil diunduh dan SHA-256 diverifikasi.
- Model asli berhasil dimuat; detection blank frame menghasilkan nol wajah,
  recognition synthetic crop menghasilkan tensor finite berukuran 1 × 512.
  Tidak ada foto peserta digunakan atau disimpan.
- Model identifier aktual:
  `buffalo_m:sha256:1b19f62653ee72944c9957bc12425a7cd3ae7448007e88a6a4bfff75694e62c6`.
- Pytest dengan `FACE_TEST_REAL_MODEL=1`: **11 passed**. Tanpa opt-in:
  **10 passed, 1 skipped**. Tes health lainnya memakai test double yang jelas
  dipisahkan dari smoke model asli.
- Satu peringatan deprecation dari Starlette TestClient atas transport httpx;
  bukan kegagalan tes. Migrasi transport test ke httpx2 dapat dilakukan terpisah.
- `pip check`: tidak ada dependency yang rusak.
- Uvicorn pada `127.0.0.1:8001`: probe HTTP asli menghasilkan **200 ready**
  dengan key, dan **401** tanpa key. Service lokal dijalankan untuk verifikasi.
- `php artisan migrate:status`: seluruh enam migrasi existing sudah diterapkan;
  tidak ada migrasi/seed dijalankan pada tahap ini.
- Tes Laravel targeted `disabled_ai_refuses_enrollment`: **1 passed, 2 assertions**.
  Suite Laravel penuh tidak diulang pada tahap ini karena binding/fitur existing
  tidak diubah. PHP lint dan Pint config baru lulus; git diff --check lulus.
- Belum diuji: wajah peserta nyata, alignment/quality endpoint, recognition identity,
  enrollment, attendance E2E, FAR/FRR, threshold, latency benchmark, liveness,
  browser sensor, dan deployment. Hasil synthetic inference bukan evaluasi identitas.

## File tahap 6.1

- Baru: `ai-service/{config,main,model_runtime,schemas}.py`, `.env.example`,
  `pytest.ini`, `requirements.txt`, `requirements-lock.txt`.
- Baru: `ai-service/scripts/{init_env,install_model,check_health}.py` serta
  `ai-service/tests/{conftest,test_health,test_install_model,test_real_model}.py`.
- Baru: `config/face.php`, dokumen ini dan `docs/face_recognition_evaluation.md`.
- Diubah: `.gitignore`, `.env.example` root dan README.
- Artefak lokal ignored: service `.env`, `.venv`, `.models`, cache test/Python.
  Tidak ada perubahan database, kontrak/binding AI existing, atau commit/push.

## Tahap berikutnya

6.2: endpoint ekstraksi, validasi satu wajah/kualitas/normalisasi, tes mock dan
model asli. 6.3: consent enrollment 3–5 sampel, encrypted embedding, re-enroll/delete
dan audit HR. 6.4: sesi kiosk terotorisasi dan transaksi attendance. 6.5: evaluasi
peserta terpisah, FAR/FRR/latency dan review keamanan.

Model pralatih InsightFace dibatasi untuk riset nonkomersial menurut dokumentasi
resmi. Penggunaan proyek ini adalah prototype portofolio nonkomersial sesuai scope
pengguna. Tidak ada liveness/anti-spoofing yang diklaim atau diaktifkan.
