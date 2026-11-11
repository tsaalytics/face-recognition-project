# Phase 4 - Employee Module

Tanggal: 11 November 2026 (Asia/Jakarta).

## Hasil
- Portal Employee, absensi sendiri dengan rentang tanggal dan pagination.
- Daftar/status/detail koreksi dan izin sendiri, termasuk catatan keputusan HR.
- Form koreksi: Check In/Out, tanggal dan waktu yang dimohonkan, alasan, consent,
  foto canvas dari getUserMedia, timestamp perangkat dan lokasi Geolocation API.
- Kamera/lokasi hanya diminta atas aksi pengguna setelah consent. Izin ditolak,
  insecure context, sensor tidak tersedia dan timeout memberikan pesan/alternatif HR.
  Tidak ada foto/lokasi pengganti atau file picker koreksi.
- Kamera dihentikan setelah capture/stop/consent dicabut/pagehide.
  Bukti dihapus dari memori form saat consent dicabut; callback lama diabaikan.
- Form izin/sakit/cuti: tanggal, alasan dan dokumen opsional.
- Foto/dokumen private, MIME/ukuran tervalidasi, path acak server.
- Server menetapkan pemilik, pending, is_demo=false, session dan submitted_at.
- Duplicate correction pending dan rentang izin pending/approved yang overlap ditolak.
- Submission memakai transaction dan lock profil; berkas baru dibersihkan jika
  transaksi gagal. Rate limit 10 submission/menit.
- Request tidak langsung mencatat absensi; service approval HR Phase 3 tetap digunakan.
- PrivateEvidenceService bersama untuk HR dan pemilik, policy view sebelum file access.
- Profil belum terhubung: halaman history kosong, submission ditolak.
  Profil nonaktif: bisa membaca riwayat sendiri, tidak bisa mengajukan.
- Tidak ada migration atau dependency runtime baru.

## File baru
- app/Http/Controllers/Employee/PortalController.php
- app/Http/Requests/Employee/{HistoryRequest,CorrectionRequest,LeaveRequest}.php
- app/Services/{EmployeeSubmissionService,PrivateEvidenceService}.php
- resources/views/employee/{home,attendance}.blade.php
- resources/views/employee/requests/{index,show}.blade.php
- resources/views/employee/corrections/create.blade.php
- resources/views/employee/leaves/create.blade.php
- resources/js/correction-form.js
- tests/Feature/EmployeeModuleTest.php
- tests/js/correction-form.test.js
- docs/phase-4.md

## File diubah
- routes/web.php: scoped Employee routes, throttle submission.
- app/Http/Controllers/Hr/ApprovalController.php: memakai shared private evidence service.
- resources/js/app.js: inisialisasi form kamera.
- resources/views/components/layout.blade.php: navigasi Employee.
- package.json: script test:js menggunakan node:test bawaan.
- README.md dan docs/architecture.md.
- Pint merapikan beberapa file HR tanpa perubahan perilaku.

## Verifikasi aktual
- php artisan test: **63 passed, 578 assertions**.
  16 tes Employee baru; seluruh regression Phase 1-3 tetap lulus.
- Mencakup ownership daftar/detail/file, role/guest, unlinked/inactive profile,
  server-owned fields, private photo, validasi foto MIME/size/GPS/consent,
  timestamp usang/future/timezone, duplikasi pending, pagination/filter,
  izin optional attachment/MIME/size/range/overlap/presence,
  alur submit Employee -> approve HR -> attendance owner,
  penolakan write/decision Employee, dan file cleanup saat database gagal.
- npm.cmd run test:js: **7 passed**.
  Simulasi consent/action, camera denial, location denial, live canvas capture +
  granted coords + multipart/422/success, revoked consent/late callback,
  insecure context dan jaringan terputus.
- Kegagalan awal dua tes JS berasal dari mock FormData yang salah mewarisi Map;
  adapter tes diperbaiki dan seluruh tes JS lulus.
- npm.cmd run build: berhasil.
- php artisan view:cache: berhasil.
- MySQL smoke dengan user/profil sintetis: submit koreksi + foto privat,
  HR approve, submit leave + HR approve, 3 attendance terverifikasi.
  Outer transaction di-rollback dan file privat dibersihkan;
  sisa user/profil/file smoke = 0. Data permohonan pengguna tidak diputuskan.
- PHP lokal mendukung upload; batas aplikasi 5 MB. php.ini tidak diubah.
- Tidak ada error pengujian tersisa pada cakupan ini.

## Batas pengujian dan fitur
- Tes sensor memakai mock API/DOM, bukan browser atau kamera/GPS fisik.
  QA visual desktop/mobile, grant/deny fisik dan browser E2E belum dilakukan.
- Konkurensi multi-worker, deployment HTTPS dan tes jaringan nyata belum dilakukan.
- CSRF memakai middleware Laravel; feature tests framework menonaktifkannya secara default.
- Server dapat memvalidasi format/rentang/berkas, tidak membuktikan sensor asli/live.
  Foto, timestamp dan GPS bukan bukti identitas; keputusan akhir HR.
- Timestamp capture diterima dalam 15 menit terakhir dengan toleransi maju 5 menit.
  Lokasi dibutuhkan, tanpa fallback. Koordinat valid tidak berarti lokasi terpercaya.
- Waktu absensi memakai Asia/Jakarta, sesi regular hari yang sama.
  Tidak ada shift lintas tengah malam, saldo cuti, kalender libur,
  edit/batal request, notifikasi eksternal atau AI.
- Tidak ada kebijakan retensi/automatic deletion baru. Bukti tersimpan sebagai
  bagian permohonan; perusahaan perlu menetapkan retensi sebelum penggunaan nyata.

## Menjalankan dan mencoba
Dari root: npm.cmd run build, php artisan optimize:clear, php artisan serve.
Tidak perlu migration atau seed ulang untuk upgrade Phase 3.
Login employee@facetrack.test / DemoFaceTrack!2026, buka /employee.
Gunakan localhost/127.0.0.1 pada komputer server, atau HTTPS valid untuk perangkat lain.
HTTP LAN biasa bukan secure context untuk kamera/lokasi.

1. Buka Koreksi saya -> Ajukan koreksi.
2. Isi tanggal/waktu/alasan, baca dan centang consent.
3. Buka kamera -> Ambil foto -> Ambil lokasi -> Kirim.
4. Login HR, tinjau request/bukti, approve atau reject.
5. Login Employee dan periksa detail keputusan serta absensi.
6. Coba menolak izin perangkat: form harus tetap tidak dapat dikirim
   dan menampilkan alternatif HR. Ini masih perlu diverifikasi pada perangkat nyata.
7. Coba Izin saya dengan/tanpa dokumen; pantau keputusan HR.
Jangan db:seed ulang database yang sudah dipakai untuk menilai data contoh.
