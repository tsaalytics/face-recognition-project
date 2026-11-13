# Rencana evaluasi face recognition

Status 6.1: belum ada evaluasi peserta atau angka accuracy/FAR/FRR/latency.
Synthetic model warm-up membuktikan runtime berjalan, bukan kemampuan mengenali orang.

Gunakan peserta dengan persetujuan eksplisit. Enrollment dan probe harus berbeda
frame/sesi pengambilan; pisahkan cohort pengaturan threshold dari pengujian akhir.
Sediakan probe orang yang tidak terdaftar dan kondisi cahaya yang tercatat.
Jangan commit foto, vektor, identitas peserta, atau hasil individu.

Pada 6.5 script evaluasi harus melaporkan:

- Accuracy identifikasi terbuka: identitas benar untuk known, Unknown untuk unknown.
- FAR: unknown diterima sebagai salah satu anggota gallery / seluruh unknown probe.
- FRR: known ditolak / seluruh known probe; salah identifikasi known dilaporkan terpisah.
- Rata-rata dan P95 inference latency; warm-up dipisahkan dari steady state.
- Waktu end-to-end dari request Laravel sampai respons pencatatan transaksi selesai;
  pisahkan camera/user interaction time dan network timing.
- Ukuran gallery, CPU, RAM, versi dependency/model identifier, threshold, kualitas,
  pencahayaan, jumlah probe dan ketidakpastian hasil.

Angka 2,17 detik/orang berasal dari referensi Capstone yang disebut pengguna dan
belum diverifikasi pada proyek ini. Perbandingan nanti harus menjelaskan perangkat,
dataset, transport, warm-up dan definisi durasi; jangan menyebut peningkatan sebelum
benchmark yang sebanding selesai.

Tanpa liveness, foto/video dapat menyerang sistem. Recognition bukan bukti kehadiran
fisik yang kuat; liveness dan evaluasi spoofing diperlukan sebelum penggunaan nyata.
