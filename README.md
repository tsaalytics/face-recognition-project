# FaceTrack AI

Sistem absensi karyawan berbasis Laravel 13, PHP 8.3, Blade, Tailwind CSS 4,
Vite dan MySQL. Implementasi saat ini: **Phase 3 - HR Module**.

## Yang tersedia
- Login/logout session Laravel, CSRF, validasi dan rate limit login.
- Role Admin, HR, Employee; role middleware, pencabutan sesi akun nonaktif.
- Policies kepemilikan data, HR-only approval permission, Admin attendance read-only.
- Tujuh tabel domain, foreign keys, indeks unik employee/date/session.
- Models/relasi, seed sintetis berulang, kontrak AI yang nonaktif.
- Login dan beranda role bertema purple/magenta; aset lokal melalui Vite.

Phase 2 menambahkan dashboard Admin, pembuatan/edit/nonaktif/aktif akun HR dan Employee,
perubahan role, direktori karyawan dan monitoring absensi dengan filter serta pagination.

Phase 3 menambahkan dashboard HR, CRUD profil, monitoring/export CSV, halaman
enrollment nonaktif, detail bukti privat dan keputusan koreksi/izin dengan audit.

Belum tersedia: modul Employee untuk pengajuan mandiri, kamera/GPS, upload bukti,
integrasi AI dan pengaturan shift lintas tengah malam.
Tidak ada data pribadi/biometrik nyata. Foto demo hanyalah berkas teks berlabel sintetis;
koordinat 0,0 pada fixture bukan lokasi perangkat dan tidak boleh dipakai sebagai bukti.

## Persyaratan
PHP >=8.3 dengan pdo_mysql, mbstring, openssl, fileinfo, xml, curl dan zip;
Composer 2; Node 22.11+ dan npm; MySQL lokal.
Pengujian terisolasi menggunakan pdo_sqlite.
Vite 6 + laravel-vite-plugin 1 dipilih agar kompatibel Node lokal 22.11.
Dependency PHP/JS dikunci dalam composer.lock dan package-lock.json.
MySQL 5.7 lokal bersifat legacy; gunakan MySQL 8 pada lingkungan baru.

## Instalasi (PowerShell)
Jalankan dari direktori proyek:

```powershell
composer install
Copy-Item .env.example .env
php artisan key:generate
npm.cmd ci
npm.cmd run build
```

Buat database kosong khusus aplikasi, jangan gunakan database aplikasi lain:

```sql
CREATE DATABASE facetrack_ai CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

Atur .env: APP_ENV=local, APP_URL=http://127.0.0.1:8000, DB_CONNECTION=mysql,
DB_HOST=127.0.0.1, DB_PORT=3306, DB_DATABASE=facetrack_ai, DB_USERNAME,
DB_PASSWORD sesuai MySQL Anda. Jangan commit .env atau credential asli.

```powershell
php artisan migrate
php artisan db:seed
php artisan serve --host=127.0.0.1 --port=8000
```

Buka http://127.0.0.1:8000/login. Untuk perubahan aset, jalankan
npm.cmd run dev di terminal kedua. Build produksi tidak memerlukan server Vite.
Jangan menjalankan migrate:fresh pada database yang berisi data penting.
Jangan jalankan composer setup pada database yang belum disiapkan.

## Akun demo (khusus local/testing)
| Role | Email | Password |
|---|---|---|
| Admin | admin@facetrack.test | DemoFaceTrack!2026 |
| HR | hr@facetrack.test | DemoFaceTrack!2026 |
| Employee | employee@facetrack.test | DemoFaceTrack!2026 |

Seeder hanya berjalan di local/testing, mengembalikan password/status akun demo
pada setiap eksekusi, dan tidak boleh dipakai dengan data produksi.
Enam profil, tiga divisi, enam absensi bertanda seed_fixture,
satu koreksi pending dan satu cuti pending disediakan untuk pengujian berikutnya.
Tidak ada absensi yang mengaku bersumber face recognition.
Berkas bukti privat berada di storage/app/private; tidak perlu storage:link.
Endpoint bukti privat pada Phase 3 hanya tersedia bagi HR melalui detail permohonan.

## Pengujian
```powershell
php artisan test
php artisan migrate:status
npm.cmd run build
```

PHPUnit memakai SQLite in-memory, cache/session array, dan tidak menghapus data MySQL.
Lihat [rancangan dan aturan fase berikutnya](docs/architecture.md).
Laporan Phase 1: docs/verification.md. Phase 2: docs/phase-2.md. Phase 3: docs/phase-3.md.


## Modul Admin (Phase 2)
Masuk sebagai admin@facetrack.test dan buka /admin.
- Dashboard: total semua profil karyawan, hadir hari ini (karyawan aktif unik
  dengan check-in berstatus present/late), belum hadir (aktif minus hadir),
  dan total akun termasuk nonaktif. Belum hadir bukan keputusan absen final.
- /admin/users: pencarian nama/email, filter role/status, pagination 15 baris.
  Tambah akun HR/Employee, edit nama/email/role/status, reset password opsional.
  Password minimal 12 karakter dengan huruf besar/kecil, angka dan simbol.
- Akun Admin dilindungi dari perubahan melalui modul ini. Akun Employee yang
  sudah memiliki profil tidak dapat diubah menjadi HR sebelum tautan profil dilepas HR.
- Perubahan email, role, status atau password merotasi remember token dan
  menghapus sesi pengguna jika SESSION_DRIVER=database (konfigurasi lokal).
  Tidak ada penghapusan akun permanen.
- /admin/employees: semua profil, pencarian nama/NIK/email, filter divisi/status.
- /admin/attendance: semua riwayat, pencarian nama/NIK, filter rentang tanggal,
  divisi dan status. Sumber seed_fixture jelas ditampilkan sebagai data demo sintetis.
- Karyawan tanpa catatan tidak otomatis diberi baris absent.
  Tidak ada endpoint edit/hapus/approval attendance untuk Admin.
- Profil karyawan, approval dan export kini tersedia pada Phase 3 (lihat di bawah).

Tidak ada migration baru di Phase 2. Untuk instalasi Phase 1 yang sudah berjalan:
npm.cmd ci, npm.cmd run build, php artisan optimize:clear, lalu php artisan serve.
Jangan seed ulang database yang telah Anda kelola melalui Admin: seeder demo akan
mengembalikan data akun contoh, sebagaimana dijelaskan di atas.


## Modul HR (Phase 3)
Upgrade dari Phase 2:

```powershell
php artisan migrate
npm.cmd run build
php artisan optimize:clear
php artisan serve
```

Migrasi baru hanya menambahkan indeks unik keputusan di approval_logs.
Database lokal pada sesi pengembangan ini sudah dimigrasikan. Tidak perlu seed ulang.
Login hr@facetrack.test / DemoFaceTrack!2026, lalu buka /hr.

- Dashboard: karyawan aktif, hadir/terlambat/belum hadir, tren 7 hari,
  statistik divisi, serta jumlah/link koreksi dan izin pending.
- Profil: tambah/edit NIK, kontak, divisi, jabatan, status, serta hubungkan/lepas
  akun Employee yang dibuat Admin. Akun baru harus aktif dan belum dipakai;
  tautan akun nonaktif yang sudah ada dapat dipertahankan.
- Monitoring: filter nama/NIK, tanggal, divisi, status, pagination dan CSV
  yang mencakup seluruh hasil filter, bukan hanya halaman aktif.
- /hr/enrollment hanya menampilkan status; AI tetap nonaktif.
- /hr/corrections dan /hr/leaves menampilkan daftar, detail, bukti privat,
  status dan riwayat keputusan. Reject wajib memiliki catatan.
- Approval final sekali saja, tidak dapat diubah/dibatalkan melalui aplikasi.
  HR tidak boleh menilai permohonan miliknya sendiri.
- Persetujuan koreksi membuat/memperbarui sesi regular dengan sumber hr_approved.
  Check-out memerlukan check-in; jam pulang tidak boleh lebih awal.
  Tanggal/waktu harus sesuai hari yang sama dan tidak di masa depan.
  Terlambat dihitung berdasarkan config/attendance.php (08:00, toleransi 0 menit).
- Persetujuan izin membuat status leave pada sesi regular setiap tanggal,
  termasuk akhir pekan. Belum ada kalender hari kerja/libur atau saldo cuti.
  Rentang maksimum 366 hari. Konflik dengan hadir atau izin approved lain
  menghasilkan error dan tidak menyimpan keputusan parsial.
- Keputusan menulis reviewer, timestamp server, transisi dan catatan ke approval_logs.
  Bukti/foto, GPS dan timestamp perangkat tetap memerlukan penilaian manusia.
- CSV memakai UTF-8 BOM, escaping sel formula, dan label data demo.
- Berkas private hanya diberikan lewat rute HR berotorisasi dan MIME allowlist.
  Foto koreksi non-demo maksimal 5 MB (JPEG/PNG/WebP).
- Fixture koreksi Phase 1 tetap berupa teks berlabel demo, bukan foto kamera.
  Setelah HR menyetujui fixture ini, sumber hr_approved berarti keputusan HR
  atas contoh sintetis, bukan hasil AI.

Form pengajuan Employee dan pengambilan kamera/lokasi masih Phase 4.
Jangan menjalankan db:seed setelah menilai demo: seeder Phase 1 dirancang untuk
data contoh awal dan dapat mengembalikan status fixture.
