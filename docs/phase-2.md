# Phase 2 - Admin Module

Selesai 11 November 2026 (Asia/Jakarta).

## Implementasi
- Dashboard: total karyawan dan user, hadir unik hari ini, belum hadir karyawan aktif.
- Akun: daftar/search/filter/pagination, tambah HR/Employee, edit, role,
  aktif/nonaktif, reset password opsional dan konfirmasi password.
- Akun Admin tidak dapat diubah; akun yang terkait profil harus tetap Employee.
- Service transaksi dan row lock untuk update akun, policy dan Form Requests;
  role/status tidak dibuka untuk mass assignment umum.
- Perubahan email/role/status/password merotasi remember token dan menghapus
  sesi database target. User nonaktif tetap ditolak middleware Phase 1.
- Direktori seluruh karyawan: divisi, jabatan, email, telepon, akun dan status.
- Riwayat absensi: tanggal, sesi, divisi, masuk/pulang, status dan sumber.
- Filter rentang tanggal/nama/NIK/divisi/status, pagination 15 baris,
  empty state, error summary, flash success dan loading tombol simpan.
- Navigasi mobile dapat dibuka/ditutup; tabel dapat digeser horizontal.
- Absensi hanya memiliki rute GET; tidak ada endpoint perubahan/approval.
- Tidak ada AI atau data face recognition baru, schema/migration dan seeder
  Phase 1 tidak diubah.

## File Phase 2
- app/Http/Controllers/Admin/MonitoringController.php
- app/Http/Controllers/Admin/UserController.php
- app/Http/Requests/Admin/MonitorRequest.php
- app/Http/Requests/Admin/UserRequest.php
- app/Policies/UserPolicy.php
- app/Services/AdminMonitoringService.php
- app/Services/UserAccountService.php
- resources/views/admin/dashboard.blade.php
- resources/views/admin/users/index.blade.php
- resources/views/admin/users/form.blade.php
- resources/views/admin/employees.blade.php
- resources/views/admin/attendance.blade.php
- resources/views/components/status.blade.php
- resources/views/components/monitor-filters.blade.php
- tests/Feature/AdminModuleTest.php
- Diubah: routes/web.php, resources/views/components/layout.blade.php,
  resources/css/app.css, resources/js/app.js, README.md, docs/architecture.md.

## Verifikasi aktual
- php artisan test: 26 passed, 215 assertions (termasuk 12 tes baru modul Admin).
- Tes: role dan guest pada semua halaman Admin; create HR/Employee;
  hashing/validasi password; duplicate email; role/status invalid;
  perlindungan Admin; edit role/nonaktif tanpa mengganti password;
  linked employee tidak bisa diubah HR; reset mencabut sesi/remember token;
  search/filter/pagination; tanggal invalid; empty states; dashboard deduplikasi
  dan pengecualian karyawan nonaktif; tidak ada endpoint mutasi absensi.
- npm.cmd run build: berhasil.
- php artisan view:cache: berhasil.
- Pint diterapkan.
- route:list --path=admin: delapan rute, mutasi hanya pada akun pengguna.
- Smoke check service monitoring pada MySQL lokal: berhasil membaca dashboard,
  pagination karyawan dan attendance.
- Tidak ada error pengujian tersisa pada cakupan ini.
- UI browser, interaksi toggle mobile dan browser E2E belum diverifikasi.
  Tes rendering menggunakan Blade melalui feature test; bukan QA visual.
- Workflow keputusan approval, upload/download bukti dan integrasi AI belum dibuat.

## Menjalankan
Tidak perlu migration atau seed ulang untuk upgrade dari Phase 1.
Jalankan npm.cmd run build, php artisan optimize:clear, php artisan serve.
Login admin@facetrack.test / DemoFaceTrack!2026 lalu buka /admin.
Jangan seed ulang setelah mengelola akun demo, karena seeder akan mereset data demo.
