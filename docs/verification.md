# Verifikasi Phase 1

Tanggal: 11 November 2026 (Asia/Jakarta).

## Hasil aktual
- Laravel 13.42.0 pada PHP 8.3.12, Composer 2.8.4.
- Node 22.11.0, Vite 6.4.6, Tailwind CSS 4.
- MySQL 5.7.39 lokal: koneksi, seluruh 4 migration batch 1 berhasil.
- db:seed berhasil dan dijalankan ulang tanpa duplikasi.
- PHPUnit SQLite in-memory: 14 tes lulus, 106 assertions.
- Build Vite berhasil dari root proyek.
- Blade view:cache berhasil.
- composer validate --no-check-publish: valid.
- npm install: 0 vulnerabilities dilaporkan saat instalasi.
- Route list: tepat 7 rute aplikasi; tidak ada registrasi publik atau
  endpoint approval/AI/upload.
- Pint diterapkan pada kode PHP.

## Cakupan tes
Login/logout ketiga role; redirect tamu; matriks silang role 403;
password salah dan akun nonaktif; pencabutan sesi; rate limiting;
policy kepemilikan employee/attendance; HR-only approval permission;
Admin tidak dapat mengubah/menghapus/membuat attendance;
proteksi mass assignment role/status; unique attendance session;
seeding berulang; AI unavailable, verify/enroll melempar exception.

Tes izin approval belum menguji transaksi keputusan: service operasional belum dibuat.
CSRF memakai middleware Laravel; tes feature Laravel menonaktifkan pemeriksaan
CSRF secara default. Belum dilakukan browser E2E/QA visual desktop-mobile,
uji deployment, atau pengujian MySQL 8.
Tidak ada error pengujian yang tersisa pada cakupan Phase 1.
Kegagalan awal seed SQLite karena perbandingan tanggal telah diperbaiki
menggunakan whereDate pada pencarian fixture.

## File utama yang dibuat/diubah
- Skeleton Laravel standar, composer.json/lock, package.json/lock, vite.config.js,
  phpunit.xml, .env.example, config/app.php, config/filesystems.php,
  config/attendance.php, bootstrap/app.php.
- app/Enums/Role.php.
- app/Models/{User,Division,Employee,AttendanceRecord,
  AttendanceCorrectionRequest,LeaveRequest,ApprovalLog}.php.
- app/Http/Controllers/Auth/SessionController.php.
- app/Http/Requests/LoginRequest.php.
- app/Http/Middleware/{EnsureActive,EnsureRole}.php.
- app/Policies/{Employee,AttendanceRecord,AttendanceCorrectionRequest,LeaveRequest}Policy.php.
- app/Contracts/FaceRecognitionService.php,
  app/Services/DisabledFaceRecognitionService.php,
  app/Providers/AppServiceProvider.php.
- database/migrations/2026_11_11_000001_create_facetrack_tables.php.
- database/seeders/DatabaseSeeder.php.
- routes/web.php, resources/views/components/layout.blade.php,
  resources/views/auth/login.blade.php, resources/views/foundation.blade.php,
  resources/css/app.css, resources/js/app.js.
- tests/Feature/{ExampleTest,FoundationTest}.php, tests/Unit/ExampleTest.php.
- README.md, docs/architecture.md, docs/verification.md.
- docs/original-readme.md menyimpan README awal. Folder .git dipertahankan.

.env, vendor, node_modules, compiled assets, database lokal dan berkas bukti
tetap diabaikan Git sesuai .gitignore. Tidak ada commit atau push.
