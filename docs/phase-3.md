# Phase 3 - HR Module

Selesai: 11 November 2026 (Asia/Jakarta).

## Fitur
- Dashboard karyawan aktif, hadir unik hari ini, terlambat, belum hadir;
  tren 7 hari, statistik divisi, serta notifikasi/link permohonan pending.
- Tambah/edit profil karyawan, divisi/jabatan/kontak/status dan tautan akun.
  Hanya akun Employee aktif yang belum dipakai dapat dihubungkan;
  tautan nonaktif yang sudah ada dapat dipertahankan atau dilepas.
- Monitoring absensi berfilter/pagination dan download CSV seluruh hasil filter.
  Sel formula CSV dinetralkan, data sintetis diberi label.
- Face enrollment placeholder tanpa aksi AI atau embedding.
- Daftar/filter/detail koreksi dan izin, foto/dokumen privat, koordinat/link peta,
  tanggal, waktu perangkat/server, alasan, keputusan dan riwayat.
- ApprovalService: transaksi, lock Employee bersama + request + attendance,
  Gate di dalam transaksi, HR aktif, bukan permohonan sendiri.
- Approve koreksi membuat/memperbarui sesi regular dengan sumber hr_approved;
  memvalidasi urutan check-in/out, tanggal, waktu, koordinat dan bukti non-demo.
- Approve izin membuat status leave setiap hari rentang. Konflik dengan
  kehadiran/izin approved lain ditolak tanpa keputusan atau attendance parsial.
- Log keputusan reviewer, waktu server, transisi dan notes. Model log menolak
  update/delete; tepat satu FK request dan HR aktif wajib pada pembuatan.
- Migrasi baru menambahkan unique FK koreksi/izin pada approval_logs,
  sehingga satu request hanya dapat memiliki satu keputusan final.
- Private file route dibatasi HR dan policy view, prefix/path serta MIME allowlist,
  no-store dan nosniff. Bukti tidak dibuka melalui public/storage.

## Aturan dan batas
- Shift hanya regular pada hari yang sama; lintas tengah malam belum didukung.
- Terlambat: jam masuk setelah 08:00 (toleransi 0); configurable di config/attendance.php.
- Leave maksimum 366 hari, termasuk akhir pekan; belum ada kalender kerja/libur,
  saldo cuti, atau pembatalan/revisi keputusan.
- Belum hadir adalah aktif minus hadir, termasuk yang izin; bukan status absen final.
- Tren memakai status aktif profil saat query, bukan snapshot headcount historis.
- Foto, timestamp perangkat dan GPS bukan bukti identitas terpercaya;
  keputusan akhir tetap HR. Fixture koreksi ditandai demo, tanpa foto/GPS nyata.
- Tidak ada form pengajuan Employee, upload, kamera/GPS atau AI (Phase 4/mendatang).
- Perlindungan model log berlaku pada jalur aplikasi, bukan SQL langsung oleh DBA.
- Shared lock dirancang menyerialkan keputusan. Tes paralel multi-worker/deadlock
  belum dilakukan; SQLite feature tests tidak membuktikan perilaku konkurensi MySQL.

## Verifikasi aktual
- php artisan test: **47 passed, 418 assertions** (21 tes HR baru).
- Regression Phase 1 dan Phase 2 tetap lulus.
- Mencakup:
  akses route/aksi HR oleh guest/Admin/Employee; CRUD dan link profil;
  validasi profil dan enrollment forgery; mempertahankan link akun nonaktif;
  create/update attendance melalui approval; status terlambat; urutan check-in/out;
  keputusan ulang; penolakan wajib catatan; rollback konflik;
  larangan self-approval; inactive employee; tanggal/session invalid;
  izin multi-hari, konflik hadir/overlap/rentang invalid;
  file authorization, MIME, path traversal, foto terlalu besar, koordinat invalid;
  CSV filter/lintas halaman/formula injection; statistik/tren dan pending lists.
- MySQL 5.7.39 lokal: migrasi indeks unique berhasil.
- MySQL smoke: fixture sintetis baru, approve correction + approve leave + audit
  menghasilkan 3 attendance. Transaksi luar di-rollback; sisa profil SMOKE = 0.
  Tidak melakukan seed ulang atau memutuskan permohonan pengguna.
- npm.cmd run build: berhasil.
- php artisan view:cache: berhasil.
- Pint diterapkan; git diff --check tanpa error whitespace.
- Belum dilakukan QA visual browser/mobile, browser E2E, tes upload atau tes
  konkurensi paralel. Tidak ada error pengujian tersisa pada cakupan yang dijalankan.

## File baru
- app/Http/Controllers/Hr/{EmployeeController,MonitoringController,ApprovalController}.php
- app/Http/Requests/Hr/{EmployeeRequest,MonitorRequest,DecisionRequest,ApprovalFilterRequest}.php
- app/Services/{EmployeeProfileService,ApprovalService,HrDashboardService}.php
- database/migrations/2026_11_11_000002_unique_approval_decisions.php
- resources/views/hr/{dashboard,attendance,enrollment}.blade.php
- resources/views/hr/employees/{index,form}.blade.php
- resources/views/hr/approvals/{index,show}.blade.php
- tests/Feature/HrModuleTest.php
- docs/phase-3.md

## File diubah
- routes/web.php, app/Models/ApprovalLog.php, app/Services/AdminMonitoringService.php
  (query attendance bersama untuk pagination/CSV).
- resources/views/components/{layout,status}.blade.php
- README.md dan docs/architecture.md.
- Pint juga merapikan whitespace pada beberapa file Admin tanpa perubahan perilaku.

## Menjalankan / mencoba
Migrasi MySQL lokal sudah diterapkan. Dari root proyek jalankan php artisan serve.
Buka /login dengan hr@facetrack.test / DemoFaceTrack!2026, lalu /hr.
Untuk checkout baru: composer install, npm.cmd ci, konfigurasi .env/database,
php artisan key:generate, php artisan migrate, npm.cmd run build.
Hanya instalasi demo kosong yang memerlukan php artisan db:seed.

Tinjau fixture koreksi dan izin dari dashboard. Keputusan sekali final;
jangan db:seed ulang database yang sudah dipakai menilai fixture karena seeder
Phase 1 dapat mengembalikan status data contoh.
