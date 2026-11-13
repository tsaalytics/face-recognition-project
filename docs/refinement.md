# Employee Data, Invitation & Correction Refinement

Selesai 11 November 2026 (Asia/Jakarta).
README.md menjelaskan perilaku saat ini; laporan phase sebelumnya adalah sejarah.

## 1. Audit sebelum perubahan
Dibaca: model Employee/User/Correction/Attendance/ApprovalLog; seluruh migration;
controller Admin/HR/Employee dan Auth; role/active middleware; Form Requests,
policies, profile/account/submission/approval services, routes, Blade, JavaScript,
seeders dan feature tests. Implementasi Password Broker Laravel terpasang diperiksa.
Temuan:
- employee_number menggabungkan NIK/Employee ID; tidak ada DOB/alamat/ID terpisah.
- Admin dapat membuat dan mereset password manual; linked profile dibatasi Employee.
- User hanya memakai is_active; belum ada pending activation.
- Koreksi menerima tanggal/jam manual dan approval memakai requested_at.
- Transactions, unique attendance/session, private evidence dan policies sudah ada.
Tidak membuat ulang proyek, menjalankan migrate:fresh, reset atau seed ulang MySQL.

## 2. Database
Migration baru: 2026_11_11_000003_employee_invitation_refinement.php.
- employees.business_id VARCHAR(12) nullable UNIQUE.
- employees.date_of_birth DATE nullable.
- employees.home_address TEXT nullable.
- users.account_status VARCHAR(30) indexed: pending_activation/active/disabled.
- users.activated_at dan invitation_sent_at nullable timestamps.
- Existing is_active=0 dipetakan disabled; akun lainnya tetap active.
  activated_at legacy mengikuti created_at; password lama tidak berubah.
- attendance_records.source diperluas VARCHAR(40), mempertahankan nilai lama
  dan mendukung hr_approved_correction.
- Memakai password_reset_tokens existing untuk token broker; tidak membuat tabel token baru.
- Tidak mengganti primary key, employee_number (NIK), foreign key, atau user_id.
- down migration menghapus metadata baru; jangan rollback jika data refinement sudah ada.
  source sengaja tidak dikembalikan ke enum yang akan menolak nilai baru.

### Pelestarian data lokal
Backup sebelum migrasi:
.local-backups/facetrack-before-refinement-20261111-a78061a2.sql (22,813 bytes).
Folder backup diabaikan Git dan berada di luar public/.
Migration batch 3 berhasil.
Jumlah dan SHA-256 data seluruh kolom yang sudah ada sebelum migrasi sama sesudahnya:
users 4, employees 6, divisions 3, attendance_records 6,
attendance_correction_requests 1, leave_requests 1, approval_logs 0.
Digest per tabel disimpan lokal di .local-backups/refinement-migration-result.json.
Tidak ada password, relasi atau record historis lama diubah.

## 3. Employee
- NIK tetap employee_number, required/unique.
- business_id dihasilkan backend: random_int(0,9999) dipadatkan empat digit +
  DOB YYYYMMDD, disimpan string agar leading zero tetap ada.
- Pre-check collision dan retry ketika unique constraint DB berbenturan.
  Retry terbatas 100; collision tidak mengubah issued ID.
- Form baru/edit mewajibkan DOB/alamat/telepon string serta field profil sebelumnya.
- ID read-only, tidak mass-assignable dan tidak diterima dari input umum.
- DOB berubah tidak mengganti business_id; ID bukan password/credential.
- Legacy tanpa DOB tetap null, tidak mengarang data pribadi.
- employees:backfill-ids --dry-run menampilkan eligible tanpa menulis.
  employees:backfill-ids hanya mengisi ID kosong jika DOB diketahui.
  Perintah berulang tidak mengganti ID yang sudah ada.
- Enam profil lokal belum punya DOB, eligible=0. HR perlu melengkapinya.
- HR edit email tertaut menyinkronkan login, merotasi remember token dan mencabut
  token/sesi lama; password dan business_id tidak berubah.
- Linking legacy dipertahankan dengan role HR/Employee, akun tersedia dan email sesuai.
  Inkonistensi email/akun lama tidak ditautkan atau ditimpa otomatis.

## 4. Invitation
- Admin memilih profil aktif dengan business ID, belum tertaut akun;
  email/nama dibaca ulang dari profil. Form tidak menerima password.
- Akun pending memiliki hash secret acak yang tidak dibagikan; tidak dapat login.
- HR dan Employee dapat diberikan role pada profil yang tertaut.
- Mailable berisi nama, FaceTrack AI, Set Your Password dan batas 60 menit.
- Password Broker membuat token acak, hash di database, expiry 60 menit.
- Mail dikirim sinkron setelah account transaction. Jika gagal, akun tetap pending;
  UI menunjukkan warning dan Admin dapat resend setelah 60 detik.
- Resend membatalkan token lama, memakai lock user/cooldown dan route throttle 5/menit.
- Activation: guest route, validasi password minimal 12 + mixedCase/numbers/symbols;
  lock user, cek pending/token/expiry/profil/email; hash password, active dan consume
  token secara atomik. Token tidak dapat dipakai ulang.
- Pending tidak dapat diaktifkan langsung melalui edit Admin. Disabled tidak dapat
  login atau memakai token lama. Role/status changes mencabut sesi dan token.
- Admin edit akses hanya role/status; data identitas dari profil HR dan password
  ditentukan pengguna sendiri. Tidak ada public registration.
- admin:bootstrap memerlukan terminal interaktif/password tersembunyi dan menolak
  jika Admin sudah tersedia. Demo seeder tetap hanya local/testing, tidak mereset
  password/status akun existing atau keputusan fixture existing.

## 5. Koreksi absensi
- Tidak ada input date/time absensi di form.
- Backend mengambil satu instant now() sebagai submitted_at/requested_at dan
  attendance_date. Input palsu dari hidden field/API diabaikan.
- Approval menggunakan submitted_at, meskipun reviewer memutuskan beberapa jam/
  hari kemudian. reviewed_at di ApprovalLog terpisah.
- Source correction baru hr_approved_correction. Source hr_approved lama/leave tetap.
- requested_at lama tidak dihapus. Pending legacy yang diputuskan setelah upgrade
  memakai submitted_at sesuai aturan baru; waktu manual lama tidak dipakai approval.
- Duplicate pending check dan transaksi/lock/unique attendance tetap ada.
- Reject menyimpan keputusan/catatan tanpa mengubah attendance.
- Kamera, lokasi, private storage, ownership dan HR-only decision tetap berlaku.
- Waktu capture perangkat hanya bukti pendukung, bukan waktu absensi.
- Penulisan/pembacaan datetime aplikasi tetap Asia/Jakarta seperti data existing,
  tanpa konversi retroaktif timestamp sejarah.

## 6. File baru
- database/migrations/2026_11_11_000003_employee_invitation_refinement.php
- app/Services/EmployeeIdGenerator.php
- app/Services/InvitationService.php
- app/Mail/AccountInvitation.php
- app/Http/Controllers/Auth/InvitationController.php
- resources/views/auth/activate.blade.php
- resources/views/emails/account-invitation.blade.php
- tests/Feature/RefinementTest.php
- docs/refinement.md

### File diubah
- Models Employee/User: data profil dan casts metadata akun.
- Services EmployeeProfileService/UserAccountService: ID, relasi, role/status,
  sinkronisasi email; EmployeeSubmissionService/ApprovalService: timestamp/source.
- AdminMonitoringService: pencarian business ID.
- Controllers Admin/UserController, HR/EmployeeController/MonitoringController/
  ApprovalController, serta perapian PortalController.
- Requests Admin/UserRequest/MonitorRequest, HR/EmployeeRequest,
  Employee/CorrectionRequest, LoginRequest dan middleware EnsureActive.
- routes/web.php dan console.php: invitation/activation/resend, backfill, bootstrap.
- database/seeders/DatabaseSeeder.php: fixture lengkap/sintetis dan preserve existing.
- Admin views users/form/index, employees dan attendance.
- HR views employees/form/index, enrollment, attendance dan approvals/show.
- Employee views corrections/create dan requests/show.
- Shared layout: warning pengiriman; resources/js/app.js: searchable employee select
  dan autofill email. Warna dan struktur UI tetap.
- Tests AdminModuleTest/HrModuleTest/EmployeeModuleTest: ekspektasi aturan baru.
- .gitignore, README.md dan docs/architecture.md.

## 7. Hasil verifikasi final
- php artisan test: **76 passed, 680 assertions**.
- npm.cmd run test:js: **7 passed**.
- npm.cmd run build, php artisan view:cache, Pint: berhasil.
- git diff --check: tidak ada error whitespace (peringatan CRLF normal).
- Test mencakup format/leading zero/ID immutability, pre-check + actual DB collision,
  NIK/email/required fields, backfill eligible/missing DOB/idempotence,
  canonical profile invitation, forged email/manual password, mail fake/failure,
  hashed token, expiry, replay, resend replacement/cooldown, hashed password,
  pending/disabled login, admin bypass protection, profile email sync,
  owner isolation dan regression existing.
- Correction tests memastikan date/time input hilang, client timestamps diabaikan,
  review lima jam kemudian tetap memakai submit, log review terpisah, source tepat,
  duplicate attendance/repeat decision ditolak dan Admin tidak boleh approve.
- MySQL smoke: generated ID, profile-based account, Mail::fake, activation dan
  consumed token berhasil. Transaksi dibatalkan, profil sintetis tersisa 0.
- Tidak ada error automated test tersisa.

## 8. Cara menguji lokal dan keterbatasan
MAIL_MAILER=log dan APP_URL=http://127.0.0.1:8000, lalu php artisan config:clear.
Lengkapi profil melalui HR -> Send Invitation dari Admin.
Baca email di storage/logs/laravel.log, buka link dalam browser incognito/guest,
set password, login dan coba replay link. Mail log bukan pengiriman inbox sungguhan.
Untuk environment lain, backup dahulu dan jalankan php artisan migrate; jangan fresh/reset.
Jalankan npm.cmd run build dan php artisan serve. Tidak perlu seed ulang.
Gunakan employees:backfill-ids --dry-run sebelum backfill; data DOB wajib diketahui.

Belum diuji: SMTP inbox nyata, visual/browser E2E/search dropdown, kamera/GPS fisik,
konkurensi multi-worker/deadlock dan deployment production HTTPS.
Belum tersedia: public forgot-password untuk akun yang sudah aktif, queue/outbox
email, shift lintas tengah malam, saldo cuti/kalender kerja, undo keputusan, AI.
Tidak ada Python/embedding/AI dummy ditambahkan.
