# Phase 5 - UI dan quality assurance

Tanggal: 13 November 2026 (Asia/Jakarta).

## Cakupan implementasi
- Navigasi per role, menu mobile dengan aria-expanded dan Escape, skip link,
  fokus keyboard, serta area tabel yang dapat digulir melalui keyboard.
- Form mempertahankan input aman setelah validasi, ringkasan error mendapat fokus,
  dan pesan dihubungkan ke input melalui aria-describedby/aria-invalid.
- Tombol submit mengunci selama pengiriman dan pulih saat kembali melalui browser.
  Submit yang sudah dibatalkan handler lain tidak mengunci form.
- Penolakan HR mewajibkan catatan; foto bukti gagal dimuat menampilkan pesan pemulihan.
- Halaman 403/404/405/419/429/500/503 memberi petunjuk tanpa detail internal.
- Pengujian CSRF dengan middleware aktif, pemisahan akses role/pemilik,
  escaping alasan, status akun, serta workflow Employee ke approval HR.

Perubahan UI utama sudah berada di working tree saat fase ini dilanjutkan.
Sesi ini memeriksa implementasi tersebut, menghapus atribut tombol login ganda,
menambahkan perlindungan submit yang dibatalkan beserta tes regresinya, dan
melengkapi dokumentasi. Refinement akun/profil tetap mengikuti README.

## Verifikasi
- JavaScript: 16 tes lulus, termasuk sensor mock dan perilaku UI.
- Build Vite dan cache Blade berhasil; Pint dijalankan pada perubahan PHP.
- PHP: 85 tes lulus, 817 assertions; SQLite in-memory, termasuk 8 tes UI/CSRF.
- git diff --check lulus; hanya peringatan normalisasi CRLF.

## QA browser/perangkat yang masih diperlukan
QA visual desktop/mobile belum selesai: koneksi browser timeout dan helper Windows
melaporkan native pipe tidak tersedia. Tes DOM/HTML tidak membuktikan layout visual
atau akses kamera/GPS perangkat. Jangan menganggap fase QA perangkat sudah selesai.

Gunakan data sintetis pada database pengujian terpisah, localhost atau HTTPS valid.
Jangan menjalankan migrate:fresh atau seed ulang database operasional.

| Pemeriksaan | Langkah dan hasil yang diharapkan |
| --- | --- |
| Desktop | Pada 1280px, login tiap role; menu sesuai role, konten/tombol tidak terpotong. |
| Mobile | Pada 360px dan 390px, buka/tutup menu; tabel bergulir di dalam panel, halaman tidak melebar. |
| Keyboard | Tab menampilkan fokus; skip link menuju konten; Escape dari menu kembali ke tombol menu. |
| Validasi | Kirim input tidak valid; ringkasan terbaca, nilai aman tetap ada, password tidak terisi ulang. |
| Loading | Kirim satu kali; tombol sibuk; kembali lewat browser memulihkan tombol. |
| Bukti | Putuskan pemuatan foto; pesan gagal tampil dan tautan unduh tetap tersedia. |
| Koreksi | Consent lalu kamera/lokasi grant dan deny; tanpa consent tidak ada sensor, tanpa bukti tidak terkirim. |
| Workflow | Submit Employee, review HR, periksa waktu submit/keputusan dan riwayat pemilik; role lain ditolak. |
| Error | Periksa 404, sesi formulir kedaluwarsa, dan koneksi terputus; pesan memberi langkah pemulihan. |

AI tetap dinonaktifkan. SMTP nyata, sensor fisik, deployment HTTPS dan konkurensi
multi-worker masih memerlukan pengujian terpisah.
