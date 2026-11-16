# Phase 6.4 - Kiosk absensi wajah

Tanggal: 16 November 2026 (Asia/Jakarta).

## Implementasi

Login HR aktif lalu buka **Kiosk absensi** (`/hr/kiosk`). HR mengaktifkan sesi
selama 15 menit pada session login yang sama; kiosk tetap harus diawasi HR karena
halaman memakai akun HR. Ini bukan akun perangkat atau public kiosk terpisah.
Sesi dapat ditutup, tidak diperpanjang otomatis, dan diperiksa lagi setelah inference.
GET tidak mengaktifkan sesi. Seluruh POST memakai auth, role HR, CSRF; start dibatasi
5/menit dan scan 10/menit per pengguna oleh middleware throttle Laravel.

Peserta menyetujui pemrosesan per scan, memilih check-in/check-out, membuka kamera,
lalu menekan scan. Satu frame dikirim sebagai JSON `samples` berisi satu data URL.
Tidak ada upload file, penyimpanan foto atau persistensi browser. Kamera berhenti
setelah scan, consent dicabut, sesi habis atau pagehide; canvas dibersihkan.
Consent dikunci selama request berlangsung dan harus dipilih lagi untuk scan berikutnya.
Callback izin kamera terlambat tidak menghidupkan kamera setelah consent dicabut.

Laravel memvalidasi MIME sebenarnya, ukuran maksimal 1 MiB, dimensi 32–4096 dan
maksimal 12 juta pixel, lalu memakai FaceExtractionClient existing. Python tidak
menerima identitas karyawan. Gallery hanya profil aktif, memiliki business ID,
enrolled, embedding 512 dengan model identifier sama. Skor karyawan adalah cosine
tertinggi dari sampelnya; kandidat teratas harus melewati FACE_SIMILARITY_THRESHOLD
dan berselisih minimal 0,05 dari kandidat kedua. Margin 0,05 merupakan heuristik
prototype, belum dikalibrasi. Threshold kosong/tidak valid menolak scan, tanpa
memanggil AI. Tidak ada nilai threshold otomatis yang ditulis ke .env.

Transaksi mengunci aktor HR lalu Employee lalu attendance, memeriksa ulang status
aktor/profil dan versi enrollment. Lock Employee dipakai bersama approval HR.
Waktu/tanggal server Asia/Jakarta dan sesi regular dipakai; jam mulai 08.00 WIB,
grace 0 sesuai config attendance existing. Check-in sekali sehari per sesi;
check-out membutuhkan check-in lebih awal dan hanya sekali. Scan berulang tidak
mengubah waktu existing. Izin approved, catatan leave, koreksi HR dan sumber
existing selain face_recognition tidak ditimpa. Attendance ditandai sumber
face_recognition; tidak menyimpan skor, vektor, foto atau log scan terpisah.

Perubahan gallery peserta lain selama scan tidak diserialisasi seluruhnya; versi
kandidat terpilih diperiksa. Gallery linear O(N × 512) memerlukan dekripsi di memory,
sehingga fitur ini ditujukan untuk prototype kecil. Kegagalan internal disanitasi
agar payload/SQL biometrik tidak masuk exception log melalui controller.

## Menjalankan

Tidak ada migrasi atau seed baru. Jalankan service Python existing dan Laravel,
atur FACE_SIMILARITY_THRESHOLD secara eksplisit setelah evaluasi, kemudian jalankan
`php artisan config:clear` dan `npm.cmd run build`. Enrollment peserta harus tersedia.
Gunakan localhost/HTTPS dan peserta yang menyetujui. Untuk menolak biometrik atau
memperbaiki absensi, gunakan workflow koreksi HR existing. Jika jaringan terputus,
periksa riwayat dahulu sebelum mencoba lagi.

## Verifikasi dan batas

Suite Laravel akhir: **111 passed / 958 assertions**, termasuk 10 tes kiosk.
Cakupan tambahan: izin, perubahan versi enrollment, expiry selama inference,
frame invalid, model mismatch dan CSRF nyata. Fixture izin awal diperbaiki agar
status approved benar-benar tersimpan melalui forceFill khusus tes.
Tes JavaScript: 23 passed termasuk consent, cleanup kamera per scan, late grant dan
expiry kiosk. Build Vite dan cache Blade berhasil. Tes memakai SQLite in-memory,
mock ekstraksi dan DOM/sensor mock; tidak menulis data peserta ke MySQL lokal.

Belum diuji dengan wajah nyata, kamera fisik, QA visual desktop/mobile, deployment
HTTPS atau konkurensi MySQL multi-worker. Tidak ada klaim accuracy, FAR/FRR atau
liveness/anti-spoofing. Evaluasi threshold dan keamanan tetap Phase 6.5.
Kontrak placeholder FaceRecognitionService tetap tersedia untuk kompatibilitas;
kiosk menggunakan FaceAttendanceService dan FaceExtractionClient.
