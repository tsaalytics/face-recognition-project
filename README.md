# FaceTrack AI

Laravel 13 / PHP 8.3, Blade + Tailwind 4, Vite, MySQL.
Modul Admin, HR dan Employee tersedia. AI/face recognition belum diaktifkan.

Dokumen ini menjelaskan perilaku **setelah Employee & Invitation Refinement**.
Laporan phase-1/2/3/4 di docs adalah catatan historis, bukan spesifikasi terbaru.

## Instalasi baru
PHP >=8.3 (pdo_mysql, pdo_sqlite untuk tes, GD, mbstring, fileinfo, XML, curl, zip),
Composer 2, Node 22.11+ dan npm. Aset memakai Vite 6 yang kompatibel Node lokal.
Buat database MySQL kosong khusus aplikasi (disarankan MySQL 8 untuk lingkungan baru).

```powershell
composer install
Copy-Item .env.example .env
php artisan key:generate
npm.cmd ci
```

Atur .env: DB_CONNECTION=mysql, DB_HOST, DB_PORT, DB_DATABASE=facetrack_ai,
DB_USERNAME dan DB_PASSWORD sesuai environment; APP_URL=http://127.0.0.1:8000.
Lalu:

```powershell
php artisan migrate
npm.cmd run build
php artisan serve
```

Untuk database demo kosong dengan APP_ENV=local saja: php artisan db:seed.
Akun contoh admin@facetrack.test / hr@facetrack.test / employee@facetrack.test,
password DemoFaceTrack!2026. Seeder tidak boleh dipakai untuk provisioning produksi.
Admin pertama di database non-demo: php artisan admin:bootstrap admin@example.com
(--name opsional); password diminta secara tersembunyi. Tidak ada registrasi publik.

## Upgrade database existing tanpa kehilangan data
Backup database dahulu. Jalankan **php artisan migrate**, bukan migrate:fresh/reset.
Migrasi 000003 menambah kolom dan memperluas tipe source; tidak mengganti primary
key/NIK, menghapus record atau mengubah password pengguna lama.
Migrasi lokal pada sesi refinement sudah dijalankan; tidak perlu seed ulang.

- employees.employee_number tetap **NIK** legacy yang unik.
- employees.business_id: string 12 digit unik, empat angka random_int(0,9999)
  dengan leading zero + tanggal lahir YYYYMMDD; backend-generated dan read-only.
- date_of_birth dan home_address ditambahkan nullable demi kompatibilitas data lama.
  Form HR baru/edit mewajibkan nama, NIK, tanggal lahir, alamat, divisi, email,
  telepon string dan jabatan; ID yang sudah terbit tetap meski tanggal lahir berubah.
- Profil lama tanpa tanggal lahir tetap tanpa ID, tidak diisi tanggal lahir palsu.
  Lengkapi data melalui HR. Save profil akan menerbitkan ID yang masih kosong.
- Backfill hanya menerbitkan ID pada profil yang memiliki tanggal lahir:

```powershell
php artisan employees:backfill-ids --dry-run
php artisan employees:backfill-ids
```

Perintah dapat diulang tanpa mengganti ID. PK internal dan foreign key tetap sama.
Pada audit lokal: enam profil belum memiliki tanggal lahir, sehingga dry-run eligible=0.

## Hak akses
- Admin: dashboard, invitation/role/status akun, direktori karyawan, monitoring
  absensi read-only dengan filter/pagination. Akun Admin tidak dapat diedit
  melalui form biasa; Admin tidak mengatur password pengguna.
- HR: dashboard/tren/divisi/pending, tambah/edit profil, monitoring dan CSV,
  enrollment placeholder, detail bukti privat, approve/reject koreksi/izin.
- Employee: riwayat milik sendiri, pengajuan koreksi/izin, status dan bukti sendiri.
  Data/berkas orang lain tidak dapat diakses.
- Pengubahan email profil tertaut oleh HR menyinkronkan email login dan mencabut
  token/sesi lama. Undangan pending ke email lama harus dikirim ulang.
- Linking legacy tetap tersedia bagi HR dengan email akun yang sesuai; akun
  baru dibuat oleh Admin melalui invitation. Profil lama yang email/relasinya
  tidak konsisten memerlukan peninjauan, tidak ditautkan otomatis.

## Invitation dan aktivasi
1. HR melengkapi profil aktif sampai ID Karyawan diterbitkan.
2. Admin -> Akun pengguna -> Tambah akun: cari ID/nama, pilih karyawan,
   email otomatis read-only, pilih HR/Employee, Send Invitation.
3. Backend membaca ulang profil, memastikan aktif/tidak tertaut/email tersedia.
   Akun dibuat Pending Activation, tidak dapat login.
4. Penerima membuka Set Your Password, menentukan password sendiri.
   Minimal 12 karakter, huruf besar/kecil, angka dan simbol.
5. Token Password Broker tersimpan sebagai hash, berlaku 60 menit, sekali pakai.
   Aktivasi atomik mengonsumsi token, menyimpan hash password, mengaktifkan akun.
6. Admin dapat resend untuk Pending Activation setelah 60 detik; token lama gugur.
   Resend juga dibatasi 5 request/menit; form aktivasi dibatasi 10/menit.
7. Status akun: pending_activation / active / disabled, disinkronkan dengan
   is_active untuk kompatibilitas. Admin tidak bisa melewati proses set-password
   dengan mengubah akun pending langsung menjadi active.
8. Jika transport email gagal, akun tetap pending, UI menunjukkan kegagalan dan
   Admin dapat resend setelah cooldown. invitation_sent_at menunjukkan transport
   menerima pengiriman, bukan bukti email sampai di inbox.

## Menguji email lokal (Mail Log)
Environment lokal saat diperiksa memakai MAIL_MAILER=log.
Pastikan .env:

```dotenv
MAIL_MAILER=log
APP_URL=http://127.0.0.1:8000
```

Jalankan php artisan config:clear, buat invitation dari Admin, lalu buka
storage/logs/laravel.log dan cari email terbaru berjudul FaceTrack AI - Set Your Password.
Tautan ada di isi email log. Buka dalam private/incognito window atau logout dari
Admin terlebih dahulu, karena halaman aktivasi ditujukan ke guest.
Setelah mengaktifkan akun, coba login dan gunakan kembali link: harus ditolak.
Mail log tidak mengirim ke inbox sungguhan. Token di log bersifat rahasia;
.env, *.log dan .local-backups diabaikan Git. SMTP riil belum diuji.

## Koreksi absensi
- Form hanya jenis Check In/Out, alasan, consent, kamera dan lokasi.
  **Tidak ada input manual tanggal/jam absensi.**
- Waktu absensi koreksi = waktu server saat pengajuan diterima.
  attendance_date, requested_at dan submitted_at ditetapkan dari instant yang sama.
  Input API bernama tanggal/jam/submitted_at tidak dipercaya.
- HR menyetujui memakai submitted_at, bukan waktu approval. reviewed_at terpisah.
  Source: hr_approved_correction. Reject tidak mengubah attendance.
- requested_at lama tetap disimpan untuk riwayat; pending lama yang ditinjau
  setelah upgrade memakai submitted_at sesuai aturan baru.
- Foto/captured_at/GPS hanya bukti pendukung, bukan verifikasi identitas.
  Timestamp pengambilan perangkat berbeda dari timestamp absensi server.
- Kamera canvas/getUserMedia dan Geolocation diminta setelah consent dan aksi.
  Jika ditolak, tidak ada data palsu; hubungi HR untuk alternatif.
- Gunakan localhost/127.0.0.1 atau HTTPS valid. HTTP LAN biasa tidak memenuhi
  secure context untuk sensor. Foto/GPS fisik belum diverifikasi oleh tes browser.
- Foto JPEG/PNG/WebP maksimal 5 MB dan 4096x4096; capture maksimal 15 menit lalu
  dengan toleransi jam maju 5 menit. Berkas hanya di storage/app/private.
- Satu koreksi pending per tanggal/sesi/jenis; lock Employee dipakai bersama
  service submit dan approval. Tidak ada absensi otomatis atau hasil AI simulasi.
- Check-out memerlukan check-in; sesi regular pada hari yang sama saja.

## Izin dan laporan
Izin/sakit/cuti dengan rentang tanggal, alasan, dokumen opsional PDF/JPEG/PNG/WebP
maksimal 5 MB. Pending/approved overlap atau konflik hadir ditolak.
Approve HR membuat leave tiap hari rentang, termasuk akhir pekan, maksimum 366 hari.
Belum ada kalender kerja/libur, saldo cuti atau undo keputusan.
CSV HR mencakup seluruh hasil filter dan menetralkan formula injection.
Status belum hadir di dashboard bukan keputusan absen final.

## Tes
```powershell
php artisan test
npm.cmd run test:js
npm.cmd run build
```

PHPUnit memakai SQLite in-memory dan Mail::fake, tidak reset database MySQL.
Tes JavaScript memakai mock sensor/DOM, bukan perangkat fisik.
Laporan terbaru, daftar file dan hasil migrasi: [docs/refinement.md](docs/refinement.md).
Status UI, hasil QA dan checklist browser/perangkat: [docs/phase-5.md](docs/phase-5.md).

## Troubleshooting email/lokasi
Lihat [Gmail SMTP dan lokasi perangkat](docs/email-location-troubleshooting.md).
