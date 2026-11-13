# Gmail SMTP dan lokasi perangkat

## Temuan
Mailer efektif sebelumnya log, SMTP username/password belum terisi.
Dua email undangan tercatat di log; itu bukan pengiriman inbox.
Host/port/scheme .env sudah disiapkan untuk Gmail, tetapi MAIL_MAILER tetap log
sampai credential diisi, agar tidak mengaktifkan SMTP yang belum dapat login.

## Mengaktifkan Gmail
1. Aktifkan 2-Step Verification akun pengirim.
2. Buat App Password melalui https://myaccount.google.com/apppasswords.
   Gunakan App Password, bukan password login Gmail. Workspace dapat membatasi
   fitur ini; jika tidak tersedia hubungi administrator Google Workspace.
3. Isi langsung .env (jangan kirim credential lewat chat atau commit):

```dotenv
MAIL_MAILER=smtp
MAIL_SCHEME=smtp
MAIL_HOST=smtp.gmail.com
MAIL_PORT=587
MAIL_USERNAME=alamat-pengirim@gmail.com
MAIL_PASSWORD="APP_PASSWORD_ANDA"
MAIL_FROM_ADDRESS=alamat-pengirim@gmail.com
MAIL_FROM_NAME="FaceTrack AI"
```

MAIL_FROM_ADDRESS harus sesuai akun pengirim yang diizinkan.
Jika memakai App Password yang ditampilkan berkelompok, masukkan tanpa spasi.
Jika ada MAIL_URL lama, hapus/kosongkan supaya tidak menimpa parameter SMTP.

4. Jalankan php artisan config:clear dan restart php artisan serve bila perlu.
5. Pastikan email profil penerima benar-benar dapat menerima email,
   bukan domain demo @facetrack.test.
6. Admin -> Kirim ulang undangan setelah cooldown 60 detik.
   Periksa inbox/spam. Tautan undangan dibuka di sesi guest/incognito.
7. Jika gagal, aplikasi menunjukkan warning. Jangan menganggap timestamp pengiriman
   sebagai jaminan masuk inbox; periksa log error tanpa membagikan token/credential.

Tidak ada credential yang dibuat/ditebak oleh agent. SMTP inbox belum diuji karena
credential belum tersedia. Tidak ada email test nyata dikirim oleh agent.

Referensi:
https://support.google.com/mail/answer/185833?hl=en
https://developers.google.com/workspace/gmail/imap/imap-smtp

## Lokasi
Pesan lama menggabungkan POSITION_UNAVAILABLE (2) dan TIMEOUT (3); pesan itu
tidak cukup untuk menentukan satu penyebab pada perangkat pengguna.

Perbaikan:
- Membuka/mengulang kamera tidak lagi menghapus koordinat yang sudah didapat.
- Gagal akurasi tinggi (kode 2/3) mencoba mode standar Geolocation API sekali,
  dengan timeout 20 detik. Keduanya meminta koordinat nyata dari browser.
- Izin ditolak (1) tidak dicoba ulang/dilewati.
- Pesan berbeda untuk kode 1/2/3, lengkap dengan petunjuk pengaturan.
- Consent dicabut/halaman ditinggalkan tetap membersihkan bukti dan mengabaikan
  callback yang terlambat. Data palsu/IP lookup tidak ditambahkan.

Coba:
1. Refresh keras Ctrl+F5 untuk memuat build baru.
2. Gunakan http://localhost:8000 atau http://127.0.0.1:8000.
   Untuk perangkat lain gunakan HTTPS valid, bukan HTTP IP LAN.
3. Jika bersedia, Windows Settings -> Privacy & security -> Location:
   aktifkan Location services dan akses lokasi untuk aplikasi desktop/browser.
   Windows 10 memakai Privacy -> Location.
4. Di izin situs browser, pastikan Location diizinkan.
5. Pastikan internet tersedia; coba lagi dan tunggu hingga sekitar 35 detik
   untuk dua percobaan. Mode standar dapat memiliki akurasi lebih rendah;
   perkiraan akurasi ditampilkan.
6. Jika tetap gagal, catat kode baru (1/2/3), URL dan browser. Hubungi HR untuk
   prosedur alternatif jika perangkat tidak bisa memberikan posisi.

Referensi:
https://developer.mozilla.org/en-US/docs/Web/API/GeolocationPositionError
https://support.microsoft.com/en-us/windows/privacy/windows-location-service-and-privacy

## Verifikasi
10 tes JavaScript lulus, termasuk lokasi-sebelum-kamera, retake tidak menghapus
lokasi, retry standar, kode error dan tidak retry permission denied.
Tes feature refinement memeriksa pesan mode log tidak mengklaim pengiriman inbox.
Tes sensor menggunakan mock browser; lokasi fisik/OS belum diverifikasi.
Tidak ada perubahan database atau pengiriman email nyata dalam perbaikan ini.
