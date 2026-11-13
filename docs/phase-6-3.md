# Phase 6.3 - HR face enrollment

Tanggal: 13 November 2026 (Asia/Jakarta).

## Hasil

HR aktif dapat membuka Karyawan -> Register Face / Kelola enrollment, mencari ID
Karyawan, lalu memilih Register Face atau Re-enroll Face. Profil harus aktif dan
memiliki business ID yang sudah diterbitkan. Admin dan Employee tidak dapat membuka
atau mengubah enrollment. Penghapusan tetap tersedia bagi HR untuk profil nonaktif.

Halaman menjelaskan tujuan biometrik demo, data yang disimpan, cara menghapus,
alternatif workflow lama dan keterbatasan liveness. HR menyatakan karyawan sudah
memberikan persetujuan sebelum kamera dapat dibuka. Ini pencatatan pernyataan HR,
bukan verifikasi otomatis bahwa orang yang hadir adalah pemilik profil.

Kamera hanya dibuka setelah consent dan aksi, tanpa file picker. Tangkap 3-5 sampel
dengan variasi posisi ringan dan jeda minimal satu detik. Frame JPEG maksimal
960x720, maksimal 1 MiB/sampel di backend. Buffer foto hanya di memory browser dan
request server. Track dihentikan setelah 5 sampel, submit, reset, consent dicabut,
atau pagehide. Callback kamera lama tidak dapat menghidupkan stream setelah consent
dicabut. Canvas dibersihkan; tidak ada localStorage, sessionStorage atau preview
gambar persisten. Selama submit, consent dikunci karena request sudah dikirim;
setelah tersimpan, pencabutan dilakukan melalui Delete Enrollment.

## Backend dan penyimpanan

- Route GET/POST/DELETE `/hr/employees/{employee}/face` memakai auth/role HR dan
  policy manageFace. POST dibatasi 3 request/menit, DELETE 5/menit, serta CSRF.
- Browser mengirim JSON berisi consent, versi dan 3-5 data URL JPEG/PNG/WebP.
  Laravel mengecek format, MIME sebenarnya, ukuran dan dimensi sebelum inference.
  Sampel tidak masuk file upload, filesystem, tabel foto atau old-input session.
- Laravel meneruskan byte mentah ke `/face/extract-embedding` melalui client internal
  dengan X-API-Key, connect timeout 3 detik, timeout per request dari konfigurasi
  (maksimal 30 detik), dan anggaran total inference 25 detik. Tidak ada retry otomatis
  atau redirect. Kesalahan layanan dipetakan ke pesan lokal, tanpa body/secret AI.
- Respons harus buffalo_m, identifier SHA-256 valid, dimensi 512, angka finite dan
  norm L2 mendekati 1. Metadata yang disalin hanya lima nilai kualitas numerik.
- Semua pasangan sampel harus mencapai consistency threshold 0,65 dan identifier
  model yang sama. Deduplikasi cosine >=0,9999 mempertahankan satu embedding per
  kelompok hampir identik. Tiga input valid boleh menghasilkan satu embedding unik.
  Kedua threshold adalah heuristik **belum terkalibrasi**, dapat diatur lewat
  FACE_ENROLLMENT_CONSISTENCY_THRESHOLD/FACE_ENROLLMENT_DUPLICATE_THRESHOLD.
  Threshold absensi FACE_SIMILARITY_THRESHOLD tetap terpisah dan belum diaktifkan.
- Setelah seluruh inference valid, transaksi mengunci aktor HR lalu Employee,
  memeriksa ulang status aktor/profil dan face_enrollment_version. Permintaan stale
  mendapat 409; profil atau aktor yang berubah selama inference tidak dapat bypass.
- Re-enroll menghapus row embedding lama dan memasukkan row baru dalam transaksi
  yang sama. Status Enrolled baru ditulis setelah vektor tersimpan. Jika audit/write
  gagal, embedding lama dan status/version dipulihkan melalui rollback.
- Delete Enrollment memerlukan checkbox konfirmasi dan versi terkini. Seluruh
  embedding aktif dihapus, status not_enrolled, version bertambah dan audit dibuat.
- Tabel `employee_face_embeddings` memakai FK employees.id, indeks employee/model,
  encrypted:array pada kolom TEXT, dimensi/model/status/quality/enrolled_at/timestamps.
  Raw embedding dan quality disembunyikan dari serialisasi model. Endpoint browser
  hanya mengembalikan pesan dan versi, tidak ada endpoint download embedding.
- `face_enrollment_audits` menyimpan actor ID, employee ID, enroll/re_enroll/delete,
  version, jumlah input/tersimpan, model identifier, versi consent dan waktu server.
  Tidak menyimpan foto/vektor. Update/delete melalui model ditolak; ini bukan trigger
  database atau perlindungan dari DBA/SQL langsung.
- APP_KEY existing dipakai untuk enkripsi; jangan key:generate ulang pada database
  yang telah memiliki embedding. Kelola akses database, kunci dan backup secara privat.
  Delete/re-enroll menghapus data aktif, bukan salinan pada backup/log DB historis.

Tidak ada foto enrollment tersimpan permanen. Aplikasi tidak melakukan secure memory
erasure; PHP/Python/browser dapat menyalin memory sementara. Matikan request/response
body tracing di proxy/APM ketika memakai biometrik. Tetapkan batas body web server
sekitar 8 MiB; lima sampel base64 menambah ukuran transfer. PHP post_max_size,
memory_limit dan timeout proxy harus mendukung batas aplikasi.

## Cara menjalankan

Migrasi aditif `2026_11_13_000004_create_face_enrollments` sudah diterapkan di MySQL
lokal (batch 4); tidak ada seed/fresh/reset atau perubahan primary key existing.
Saat upgrade environment lain, backup database terlebih dahulu lalu:

```powershell
php artisan migrate
php artisan config:clear
npm.cmd run build
```

Konfigurasi server-only AI_SERVICE_URL/API_KEY/REQUEST_TIMEOUT/FACE_MODEL_NAME pada
`.env` lokal telah disiapkan dari service; key tidak ditampilkan. Existing nilai
nonkosong dipertahankan. Jangan memasukkan key ke VITE atau JavaScript.

Jalankan atau restart Python dengan kode 6.2/6.3 pada port yang dikonfigurasi:

```powershell
ai-service/.venv/Scripts/python.exe -m uvicorn main:app --app-dir ai-service --host 127.0.0.1 --port 8001 --workers 1 --no-access-log
php artisan serve
```

Login HR -> Karyawan -> Kelola enrollment -> pilih ID -> consent -> Buka kamera ->
ambil 3-5 sampel -> Simpan. Jika profil legacy belum memiliki ID, lengkapi data asli
melalui Edit profil dahulu. Jangan mengisi tanggal lahir palsu untuk melewati syarat.
Setelah sukses halaman dimuat ulang dan status/jumlah embedding/audit diperiksa.
Re-enroll memakai alur sama; Delete memerlukan konfirmasi. Gunakan localhost atau
HTTPS valid, satu peserta berizin di depan kamera.

Pada kegagalan validasi, sampel browser dibuang dan HR mengambil ulang. Pada jaringan
terputus, penyimpanan belum pasti: muat ulang untuk memeriksa status sebelum mencoba
lagi. Versi mencegah percobaan lama menimpa perubahan baru.

## Pengujian

- `php artisan test --compact`: **101 passed, 924 assertions**, termasuk 12 tes
  enrollment baru dan regresi seluruh modul existing.
- `npm.cmd run test:js`: **21 passed**, termasuk 5 tes kamera enrollment baru.
- Build Vite, cache Blade dan git diff --check berhasil. Pint diterapkan pada kode PHP.
- Migrasi MySQL lokal batch 4 berhasil; tabel embedding/audit masing-masing 0 row
  setelah verifikasi. Tidak ada data biometrik pengujian yang tertinggal di MySQL.
- Cakupan: RBAC/guest/inactive, CSRF nyata, rate limit, consent/format/count/profile,
  enkripsi + hidden serialization, konsistensi/model mismatch, deduplikasi, re-enroll,
  delete/konfirmasi, audit immutable, rollback saat audit gagal, version/aktor yang
  berubah selama inference, timeout/invalid response dan tidak flash foto ke session.
- Kegagalan awal mock HTTP yang menumpuk dan reset versi fixture stale diperbaiki;
  ekspektasi UI placeholder lama disesuaikan dengan fitur enrollment aktif.
  Tidak ada kegagalan tes tersisa.

Tes Laravel memakai SQLite in-memory dan mock HTTP untuk enrollment sukses. Tes
JavaScript memakai sensor/DOM mock. Probe HTTP terpisah menggunakan service buffalo_m
asli dan frame kosong sintetis: Laravel menerima penolakan no-face yang benar, tanpa
write profil/embedding/audit. Service probe port 8002 dihentikan setelah verifikasi.

Belum diuji: enrollment sukses dengan wajah peserta nyata, sensor kamera fisik,
QA visual desktop/mobile, kualitas/threshold lintas pencahayaan, konkurensi multi-worker
dan deployment HTTPS. Tidak ada klaim accuracy/FAR/FRR/liveness. Mock tidak membuktikan
identitas atau bahwa kamera benar-benar live. Absensi wajah menunggu Phase 6.4.

## File utama

- Migrasi `2026_11_13_000004_create_face_enrollments.php`.
- Models EmployeeFaceEmbedding/FaceEnrollmentAudit; relasi/cast Employee.
- FaceExtractionClient dan FaceEnrollmentService.
- HR FaceEnrollmentRequest/Controller; policy Employee dan route HR.
- View hr/faces/show, entry points hr/enrollment dan hr/employees/index.
- resources/js/face-enrollment.js dan inisialisasi app.js.
- bootstrap/app.php mengecualikan samples dari flash input.
- config/face.php, .env.example, footer dan README.
- tests/Feature/FaceEnrollmentTest.php, penyesuaian ekspektasi HrModuleTest,
  dan tests/js/face-enrollment.test.js.

Kontrak placeholder FaceRecognitionService tetap disabled untuk attendance; enrollment
menggunakan FaceExtractionClient baru. Tidak ada commit/push atau data peserta dibuat.
