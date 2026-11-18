# Instalasi dan pengembangan lokal

[Kembali ke README](../README.md)

Panduan ini ditujukan untuk Windows PowerShell, dari root repository, dengan database pengembangan terpisah. Laravel dan FastAPI adalah dua proses yang perlu dijalankan bersamaan. Jangan menimpa `.env` atau menjalankan seeder pada database kerja hanya untuk mencoba panduan ini.

## Prasyarat

| Komponen | Kebutuhan |
| --- | --- |
| PHP / Composer | PHP 8.3 sesuai `composer.json`; Composer 2. Pastikan lockfile cocok dengan platform melalui `composer check-platform-reqs` |
| Ekstensi PHP | PDO MySQL, PDO SQLite untuk tes, GD, mbstring, fileinfo, XML/DOM, curl, zip dan ekstensi standar Laravel |
| Node.js | Lingkungan proyek diuji memakai Node 22.11 dan npm; gunakan `npm.cmd` jika PowerShell memblokir `npm.ps1` |
| Python | CPython 3.10 x64; dependency lock lokal diuji pada Windows x64 / CPU |
| Database | Server MySQL dan database kosong khusus pengembangan; koneksi historis diuji pada MySQL 5.7.39, bukan sertifikasi semua versi |
| Kamera | Browser dengan kamera pada localhost/127.0.0.1 atau HTTPS; HTTP LAN biasa tidak memenuhi secure context |
| Runtime AI | ONNX Runtime CPU, model InsightFace lokal, RAM/disk memadai; GPU tidak diwajibkan |
| Jaringan | Diperlukan saat instalasi package/model; model browser dilayani lokal setelah build |

Jangan mengganti versi dependency Python secara acak jika wheel tidak tersedia. Periksa versi Python/arsitektur terlebih dahulu; build dari source dapat memerlukan Microsoft C++ Build Tools. Keberhasilan instalasi baru pada mesin lain belum diuji oleh audit dokumentasi ini.

## 1. Siapkan Laravel

```powershell
git clone https://github.com/tsaalytics/face-recognition-project.git
Set-Location face-recognition-project
composer install
npm.cmd ci
Copy-Item .env.example .env
php artisan key:generate
composer check-platform-reqs
```

Langkah copy dan generate key hanya untuk instalasi baru. Jangan mengganti `APP_KEY` pada instalasi yang telah menyimpan embedding tanpa rencana rotasi: embedding lama memerlukan kunci untuk dibaca.

Buat database MySQL melalui client yang tersedia, misalnya:

```sql
CREATE DATABASE facetrack_ai CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

Edit `.env` lokal. Nilai di bawah hanya placeholder, bukan kredensial siap pakai:

```dotenv
APP_ENV=local
APP_DEBUG=true
APP_URL=http://127.0.0.1:8000
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=facetrack_ai
DB_USERNAME=your_local_user
DB_PASSWORD=replace_with_local_password
MAIL_MAILER=log
MAIL_FROM_ADDRESS=hello@example.com
MAIL_FROM_NAME="FaceTrack AI"
```

```powershell
php artisan config:clear
php artisan migrate
npm.cmd run build
```

Untuk database existing, backup dahulu dan gunakan `php artisan migrate`. Jangan gunakan `migrate:fresh`, `reset`, atau seed ulang sebagai langkah upgrade.

### Akun awal

Pilih **salah satu**:

- **Demo lokal pada database khusus demo:** `php artisan db:seed`. Seeder dibatasi ke `local/testing`; membuat akun sintetis, divisi, profil, riwayat dan bukti teks demo. Akun: `admin@facetrack.test`, `hr@facetrack.test`, `employee@facetrack.test`; password demo yang didefinisikan di seeder: `DemoFaceTrack!2026`. Ini kredensial fixture publik, bukan secret lingkungan pengguna. Jangan ekspos database demo ke publik. Seeder tidak membuat embedding wajah peserta.
- **Database baru tanpa data demo:** `php artisan admin:bootstrap admin@example.com --name="Administrator"`. Jalankan di terminal interaktif; password diminta secara tersembunyi. Perintah menolak jika Admin sudah ada. Database tanpa seeder belum memiliki divisi; repository belum menyediakan CRUD divisi, sehingga provisioning divisi perlu diputuskan operator. Jangan mengasumsikan bootstrap Admin menyelesaikan seluruh master data.

Profil legacy tanpa tanggal lahir belum mendapat ID bisnis. Lengkapi profil melalui HR. Backfill opsional hanya memproses tanggal lahir yang sudah tersedia:

```powershell
php artisan employees:backfill-ids --dry-run
php artisan employees:backfill-ids
```

## 2. Siapkan FastAPI

Dari root repository:

```powershell
py -3.10 -m venv ai-service/.venv
.\ai-service\.venv\Scripts\Activate.ps1
python -m pip install -r ai-service/requirements-lock.txt
```

Jika aktivasi skrip ditolak kebijakan PowerShell, tidak perlu mengubah kebijakan sistem; panggil interpreter virtualenv secara eksplisit:

```powershell
.\ai-service\.venv\Scripts\python.exe -m pip install -r ai-service/requirements-lock.txt
```

Buat konfigurasi Python **sekali**:

```powershell
.\ai-service\.venv\Scripts\python.exe ai-service/scripts/init_env.py
```

Perintah membuat `ai-service/.env` dengan random API key dan menolak menimpa file existing. Buka file tersebut secara lokal, lalu salin nilai `AI_SERVICE_API_KEY` ke `.env` Laravel. Jangan mencetak key, memasukkannya ke browser, atau mengirimkannya ke Git. Secret harus sama pada kedua proses dan minimal 32 karakter.

Konfigurasi minimum Laravel:

```dotenv
AI_SERVICE_URL=http://127.0.0.1:8001
AI_SERVICE_API_KEY=replace_with_same_generated_secret_as_python
AI_REQUEST_TIMEOUT=15
FACE_MODEL_NAME=buffalo_m
FACE_SIMILARITY_THRESHOLD=
FACE_IDENTIFICATION_MARGIN=0.05
FACE_ENROLLMENT_CONSISTENCY_THRESHOLD=0.65
FACE_ENROLLMENT_DUPLICATE_THRESHOLD=0.9999
```

Placeholder key harus diganti. Threshold sengaja kosong sampai dipilih operator: numeric, finite, lebih dari 0 dan maksimal 1. Pada sesi pengembangan pernah dipakai `0.50` sebagai **nilai percobaan lokal**, bukan threshold terkalibrasi atau rekomendasi produksi. Bila dipakai untuk smoke test lokal berizin, hasilnya tetap belum membuktikan akurasi; nilai tersebut tidak dijadikan default repository.

Pengaturan kualitas dan `FACE_MODEL_ROOT` opsional tercantum di [template environment Python](../ai-service/.env.example). Jadwal absensi berada di [config/attendance.php](../config/attendance.php); durasi pairing/perangkat berada di [config/kiosk.php](../config/kiosk.php), bukan environment variable yang belum diimplementasikan.

### Instal model

Tinjau [lisensi model](../README.md#atribusi-dan-lisensi) sebelum mengunduh:

```powershell
.\ai-service\.venv\Scripts\python.exe ai-service/scripts/install_model.py
php artisan config:clear
```

Installer mengunduh arsip resmi `buffalo_m`, memeriksa SHA-256, lalu menyimpan hanya dua bobot inferensi beserta provenance di `ai-service/.models/models/buffalo_m/`. Runtime memeriksa integritas file saat startup dan tidak mengunduh model saat request. Jika memakai `--root` custom, samakan dengan `FACE_MODEL_ROOT` pada konfigurasi Python.

## 3. Jalankan proses secara terpisah

Buka terminal dari root proyek.

**Terminal A — Laravel:**

```powershell
php artisan serve --host=127.0.0.1 --port=8000
```

**Terminal B — Vite untuk pengembangan frontend:**

```powershell
npm.cmd run dev
```

Sebagai alternatif Vite development, jalankan `npm.cmd run build` dan gunakan aset hasil build. Prebuild/predev menyiapkan WASM detector kiosk dan memeriksa checksum model browser; deploy aset generated tersebut bersama `public/build` jika nanti menyiapkan hosting.

**Terminal C — FastAPI:**

```powershell
.\ai-service\.venv\Scripts\python.exe -m uvicorn main:app --app-dir ai-service --host 127.0.0.1 --port 8001 --workers 1 --no-access-log
```

- Laravel: `http://127.0.0.1:8000`.
- FastAPI: `http://127.0.0.1:8001`, layanan internal yang membutuhkan key.
- Vite memakai alamat/port yang dicetak terminal; ini bukan port layanan AI.
- Jika port terpakai, pilih port lain dan perbarui URL Laravel/AI terkait. Helper `check_health.py` dan `probe_image.py` saat ini hard-coded ke port 8001.
- Menjalankan `composer run dev` tidak menyalakan Python. Proses perlu dijalankan kembali setelah restart; service manager/autostart produksi belum disediakan.

## 4. Verifikasi health dan alur aplikasi

Dengan konfigurasi port default:

```powershell
.\ai-service\.venv\Scripts\python.exe ai-service/scripts/check_health.py
```

Respons yang diharapkan: HTTP 200, `status=ready`, `code=ready`, model `buffalo_m`, dimensi 512, provider CPU. Helper membaca key dari konfigurasi server tanpa menampilkannya. Ini memvalidasi layanan Python, bukan kesamaan key Laravel secara otomatis.

Untuk URL custom atau pemeriksaan dari Laravel, gunakan terminal PHP interaktif yang tidak dibagikan: kirim `GET /health` dengan header `X-API-Key` dari `config('face.api_key')`, tanpa menampilkan nilainya.

Uji manual berikut memakai peserta yang setuju:

1. Login HR, lengkapi profil aktif dan ID bisnis, lalu enroll 3–5 sampel wajah.
2. Buat perangkat pada menu Perangkat Kiosk. Masukkan kode di `/kiosk/activate` pada browser kiosk; pairing berlaku 10 menit dan sekali pakai.
3. Aktivasi mengakhiri login dashboard browser tersebut. Kredensial perangkat berlaku 30 hari menurut konfigurasi saat ini, kecuali dicabut HR.
4. Izinkan kamera. Kiosk memicu recognition ketika wajah stabil, mengikuti jadwal server. Tes pada jam tutup akan ditolak.
5. Pastikan sukses muncul pada riwayat, duplikasi ditolak, unknown ditolak, dan izin yang sudah disetujui tidak tertimpa.

Akun undangan memakai `MAIL_MAILER=log` pada development: buka email aktivasi di `storage/logs/laravel.log` secara privat. Mail log **tidak mengirim ke inbox**. URL undangan adalah secret sekali pakai; jangan publikasikan log. SMTP memakai `MAIL_HOST`, `MAIL_PORT`, `MAIL_USERNAME`, `MAIL_PASSWORD`, `MAIL_SCHEME` dan sender sesuai provider. Perubahan environment memerlukan `php artisan config:clear`; restart proses jangka panjang bila perlu. [Panduan email/lokasi](email-location-troubleshooting.md) menjelaskan penanganannya.

## 5. Pengujian

```powershell
php artisan test
npm.cmd run test:js
.\ai-service\.venv\Scripts\python.exe -m pytest ai-service/tests -q
npm.cmd run build
```

Smoke model asli, opt-in dengan bobot lokal:

```powershell
$env:FACE_TEST_REAL_MODEL = '1'
.\ai-service\.venv\Scripts\python.exe -m pytest ai-service/tests/test_real_model.py -q
Remove-Item Env:\FACE_TEST_REAL_MODEL
```

Smoke ini memakai gambar/landmark sintetis, bukan evaluasi identitas. PHPUnit memakai SQLite in-memory; jangan mengganti konfigurasi tes ke database kerja. Audit tidak menjalankan ulang migration/seeder atau instalasi dependency pada database pengguna.

## Troubleshooting

| Gejala | Pemeriksaan |
| --- | --- |
| Threshold belum dikonfigurasi | Isi nilai percobaan/evaluasi secara eksplisit; bersihkan cache; jangan menganggap default kosong sebagai kegagalan model belajar |
| Layanan AI tidak dapat dihubungi / timeout | Jalankan terminal Python, cek port dan `AI_SERVICE_URL`, lalu health. Jangan langsung menaikkan timeout tanpa mengukur |
| HTTP 401 health/extraction | Samakan shared secret Laravel–Python; restart Python jika konfigurasinya berubah |
| Health 503, `model_files_missing` | Instal bobot ke root yang benar |
| Health 503, `model_load_failed` | Periksa checksum/provenance, dependency, provider CPU dan file model |
| Wajah tidak dikenali | Periksa enrollment aktif, model identifier yang sama, kualitas frame, threshold dan margin; kegagalan ini berbeda dari koneksi AI |
| Kamera/lokasi ditolak | Periksa permission browser, HTTPS/localhost dan perangkat kamera |
| Absensi tidak tercatat walau wajah cocok | Periksa jam server, mode jadwal, check-in sebelumnya, izin dan duplikasi |
| `npm.ps1` ditolak | Gunakan `npm.cmd` |
| Undangan tidak masuk inbox | Mode `log` tidak mengirim email; konfigurasi dan uji SMTP secara terpisah |

Belum tersedia recipe deployment produksi atau Docker/CI terverifikasi. Panduan ini adalah setup development, bukan prosedur go-live.
