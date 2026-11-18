# Audit dokumentasi FaceTrack AI

[Kembali ke README](../README.md)

Tanggal audit: **18 November 2026 (Asia/Jakarta)**. Sumber utama adalah working tree lokal repository, bukan project brief atau klaim README lama. Pemeriksaan ini merupakan audit implementasi untuk penulisan dokumentasi, bukan penetration test atau sertifikasi kelayakan produksi.

## Cakupan dan bukti

| Area | Sumber yang diperiksa | Kesimpulan |
| --- | --- | --- |
| Dependensi backend | [composer.json](../composer.json), [composer.lock](../composer.lock) | PHP ^8.3, Laravel ^13.0; PHPUnit/Pint tersedia |
| Frontend | [package.json](../package.json), [package-lock.json](../package-lock.json), views dan JS resources | Blade, Tailwind 4, Vite 6, ES modules, MediaPipe 1.2.3 |
| Routing dan role | [web.php](../routes/web.php), [bootstrap](../bootstrap/app.php), middleware, controllers dan policies | Admin/HR/Employee; route existence saja tidak menjamin akses: policy direktori karyawan menolak Admin |
| Akun dan aktivasi | [UserAccountService](../app/Services/UserAccountService.php), [InvitationService](../app/Services/InvitationService.php), auth controllers | Pending activation, token broker hash/sekali pakai, aktivasi atomik; tidak ada registrasi publik |
| Model data | [models](../app/Models), [migrations](../database/migrations) | User–Employee satu ke satu; Employee–Division; kehadiran/permohonan/embedding per employee; approval ke reviewer |
| Profil dan ID | [EmployeeProfileService](../app/Services/EmployeeProfileService.php), [EmployeeIdGenerator](../app/Services/EmployeeIdGenerator.php) | NIK berbeda dari business ID; business ID dibuat server; sinkronisasi email akun terkait |
| Dashboard dan laporan | [HrDashboardService](../app/Services/HrDashboardService.php), [DashboardPresentationService](../app/Services/DashboardPresentationService.php), monitoring controllers | Ringkasan/tren/filter dan CSV HR; bukan model prediksi atau payroll |
| Koreksi / izin | [EmployeeSubmissionService](../app/Services/EmployeeSubmissionService.php), [ApprovalService](../app/Services/ApprovalService.php), request validators/policies | Waktu koreksi ditetapkan saat submit; HR final approve/reject, catatan reject wajib; transaksi dan konflik rentang |
| Bukti privat | [PrivateEvidenceService](../app/Services/PrivateEvidenceService.php), [filesystems.php](../config/filesystems.php) | Storage privat, policy akses, pemeriksaan path/MIME, no-store |
| Enrollment | [FaceEnrollmentService](../app/Services/FaceEnrollmentService.php), [request validator](../app/Http/Requests/Hr/FaceEnrollmentRequest.php), tampilan/JS HR | Consent, 3–5 sampel, versi, konsistensi dan deduplikasi; enroll/re-enroll/delete |
| Enkripsi / audit | [EmployeeFaceEmbedding](../app/Models/EmployeeFaceEmbedding.php), [FaceEnrollmentAudit](../app/Models/FaceEnrollmentAudit.php), [ApprovalLog](../app/Models/ApprovalLog.php) | Embedding encrypted:array; quality metadata tidak terenkripsi oleh cast; audit update/delete diblokir pada model, bukan jaminan immutability bagi DBA |
| Jalur AI aktif | [FaceExtractionClient](../app/Services/FaceExtractionClient.php), [FaceAttendanceService](../app/Services/FaceAttendanceService.php), [matcher](../app/Services/FaceEmbeddingMatcher.php) | FastAPI untuk embedding; Laravel melakukan pencocokan dan pencatatan |
| Kiosk | [controller](../app/Http/Controllers/Kiosk/AttendanceController.php), [aktivasi](../app/Http/Controllers/Kiosk/ActivationController.php), [device controller](../app/Http/Controllers/Hr/KioskDeviceController.php), JS kiosk | Pairing perangkat, token hash, pencabutan, scan otomatis dengan state/cooldown |
| Aturan jadwal | [AttendanceSchedule](../app/Services/AttendanceSchedule.php), [attendance.php](../config/attendance.php) | Satu sesi regular, checkout lintas tengah malam, mode ditentukan waktu server |
| Python dan model | [requirements](../ai-service/requirements.txt), [lock](../ai-service/requirements-lock.txt), [runtime](../ai-service/model_runtime.py), [engine](../ai-service/face_engine.py) | InsightFace buffalo_m, dua bobot ONNX, CPU; alignment dan embedding L2; bukan training |
| API / proteksi | [main.py](../ai-service/main.py), [config.py](../ai-service/config.py), [ProtectBiometricRequests](../app/Http/Middleware/ProtectBiometricRequests.php) | Shared secret, rate/payload limits, validasi respons, no CORS, no-store untuk jalur terkait |
| Evaluasi dan tes | [tests Laravel](../tests), [tests Python](../ai-service/tests), [evaluator](../ai-service/scripts/evaluate_faces.py) | Tes mock dan smoke sintetis tersedia; belum ada hasil cohort peserta terverifikasi |
| Setup / deployment | [root env template](../.env.example), [Python env template](../ai-service/.env.example), [scripts](../ai-service/scripts), [Vite config](../vite.config.js) | Dua proses terpisah, setup lokal Windows; tidak ditemukan CI/CD atau recipe produksi aktif |
| Dokumentasi lama | README lama, docs phase/architecture/refinement/verification | Beberapa menyebut fase saat AI belum aktif; diperlakukan sebagai catatan historis |
| Lisensi / aset | composer metadata, logo publik, [InsightFace upstream](https://github.com/deepinsight/insightface#license) | Metadata MIT belum didukung LICENSE root; library dan model memiliki ketentuan berbeda |

Installer model memeriksa SHA-256 arsip dan startup memeriksa provenance dua file. Model pre-trained disediakan terpisah dari Git. Tidak ada temuan yang mendukung klaim model dilatih dari nol, fine-tuned, atau belajar otomatis dari scan.

## Keputusan struktur dokumentasi

- README untuk konteks bisnis, fitur/status, diagram arsitektur, ringkasan AI, stack, workflow, evaluasi, privasi dan roadmap.
- [Local development](local-development.md) untuk setup PowerShell lengkap, environment, proses dan troubleshooting.
- [Recognition pipeline](recognition-pipeline.md) untuk kontrak API, matching, enrollment, dan metodologi evaluasi.
- [Screenshot checklist](screenshots/README.md) untuk aset yang belum tersedia.
- Laporan fase lama dipertahankan sebagai arsip; source code, migration, model dan database tidak diubah untuk menyesuaikan narasi.

Instruksi permintaan menyebut bahasa Indonesia pada tujuan utama dan Professional English pada bagian gaya. Dokumentasi baru memilih bahasa Indonesia sesuai tujuan utama dengan istilah teknis yang umum.

## Hasil validasi sesi ini

| Pemeriksaan | Hasil aktual | Batas interpretasi |
| --- | --- | --- |
| `php artisan test --compact` | **164 passed, 1.647 assertions** | SQLite in-memory, respons AI/mail tiruan; bukan integrasi MySQL/model nyata menyeluruh |
| `npm.cmd run test:js` | **39 passed** | Node test runner dengan DOM/sensor mock; bukan kamera fisik |
| Python pytest default | **46 passed, 1 skipped** | Smoke model asli opt-in dilewati secara default |
| `FACE_TEST_REAL_MODEL=1`, test_real_model.py | **1 passed** | Bobot asli, input blank/sintetis, alignment/embedding dan HTTP test client; bukan peserta nyata |
| `npm.cmd run build` | **Berhasil**, Vite 6.4.6 | Memverifikasi perintah aset lokal, bukan hosting produksi |
| Markdown | Code fences dan target relatif diperiksa; dua flowchart ditinjau manual | Tidak dirender dengan Mermaid CLI/GitHub pada sesi ini |
| Setup commands | Dicocokkan dengan script entrypoint, opsi CLI dan konfigurasi | Tidak melakukan fresh install, migrate atau seed ulang database pengguna |
| Secret review | Contoh memakai placeholder; nilai credential privat diperiksa agar tidak tersalin | Password fixture demo sengaja didokumentasikan sebagai fixture publik, bukan credential pengguna |

Tes Python mengeluarkan satu peringatan deprecation Starlette/TestClient terkait httpx; dependency tidak diganti karena tugas hanya dokumentasi. Tes/build Node awalnya terhalang akses filesystem sandbox, kemudian berhasil di luar sandbox setelah approval.

Angka waktu test suite tidak dipakai sebagai latency face recognition. Tidak dilakukan pengambilan foto peserta, penyimpanan embedding baru, atau pengujian identitas tanpa dataset berizin.

## Informasi yang belum terverifikasi

| Informasi | Status dan langkah yang diperlukan |
| --- | --- |
| Accuracy, FAR, FRR, salah identifikasi known | Not evaluated yet; kumpulkan cohort berizin dan jalankan kalibrasi/holdout |
| Threshold yang sesuai deployment | Belum ada hasil kalibrasi; nilai percobaan lokal bukan threshold universal |
| Mean/P95 inference dan end-to-end | Belum ada laporan peserta/hardware yang terverifikasi; evaluator tidak mengukur perjalanan lengkap Laravel |
| Liveness dan ketahanan serangan foto/video | Tidak diimplementasikan; perlu desain dan evaluasi tersendiri |
| Fairness/demografi dan skala gallery | Belum ada audit/batas kapasitas terukur |
| Kamera, browser, jaringan dan hardware target | Tes otomatis memakai mock/sintetis; butuh pengujian fisik |
| SMTP dan penerimaan undangan di inbox | Pengujian sesi ini tidak mengirim email; tes Mail fake bukan bukti delivery SMTP |
| Demo publik, deployment perusahaan, penghematan waktu HR | Tidak ada bukti terverifikasi; konteks Capstone berasal dari permintaan pemilik |
| Screenshot aman untuk publikasi | Belum tersedia di repository; daftar capture ada pada checklist |
| Lisensi aplikasi | Pemilik perlu menetapkan LICENSE eksplisit; metadata composer saja tidak dianggap keputusan baru |
| Izin komersial model | Tidak disimpulkan dari lisensi library; konfirmasi ketentuan bobot kepada pemilik model |
| Retensi/penghapusan terjadwal, backup dan rotasi kunci | Belum ada prosedur produksi yang tervalidasi |
| Instalasi bersih lintas mesin | Belum direproduksi; audit memakai dependency dan bobot lokal yang tersedia |

## Temuan yang perlu tindak lanjut terpisah

Temuan ini dicatat tanpa mengubah source code:

1. **Label sumber CSV:** controller export memetakan sumber selain seed_fixture/hr_approved_correction menjadi HR Approved. Catatan `face_recognition` belum mendapat label tersendiri; jangan mengklaim label sumber CSV wajah sudah akurat.
2. **Master divisi:** migration/model/seeder menyediakan divisi, tetapi tidak ditemukan UI CRUD divisi. Instalasi tanpa demo seed memerlukan strategi provisioning master data.
3. **Legacy binding:** interface recognition lama masih dibind ke implementasi disabled; jalur kiosk baru aktif melalui service terpisah. Penyederhanaan arsitektur dapat ditinjau nanti.
4. **Consent kiosk:** enrollment mencatat consent, sedangkan kiosk standalone tidak meminta persetujuan individual per scan. Prosedur lapangan perlu dirancang sebelum pilot nyata.
5. **Cakupan audit:** approval/enrollment logs ada, tetapi bukan full security audit trail untuk semua akses atau percobaan scan.
6. **Template environment:** variabel layanan AI utama sudah tersedia; tidak ada perubahan .env.example yang diperlukan untuk dokumentasi ini. Konfigurasi jadwal/perangkat yang ada di file PHP dijelaskan sebagai konfigurasi PHP, bukan dibuatkan environment key fiktif.

## Perintah untuk mereproduksi validasi

Jalankan dari root setelah setup dependency:

```powershell
php artisan test --compact
npm.cmd run test:js
.\ai-service\.venv\Scripts\python.exe -m pytest ai-service/tests -q
npm.cmd run build
git diff --check
```

Smoke model asli dan health check dijelaskan pada [panduan lokal](local-development.md). Tidak ada commit atau push yang dilakukan sebagai bagian audit dokumentasi.
