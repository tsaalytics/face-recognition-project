# FaceTrack AI - rancangan pengembangan

## Scope
Phase 1 menyediakan login/logout, akun aktif, RBAC, policies, schema/relasi, demo seeder dan halaman awal role.
Phase 2 menambahkan dashboard Admin, CRUD akun HR/Employee tanpa hapus permanen,
serta direktori karyawan dan monitoring attendance baca-saja.
Phase 3 menyediakan CRUD profil HR, dashboard/tren/divisi, CSV, detail bukti privat,
serta approval koreksi/izin transaksional. Form pengajuan mandiri Employee belum ada.
Tidak ada AI, face embedding, kamera, geolocation maupun absensi otomatis.

## Relasi
- User 0..1 Employee; employee.user_id nullable dan unique.
- Division 1..n Employee.
- Employee 1..n AttendanceRecord, AttendanceCorrectionRequest, LeaveRequest.
- ApprovalLog mengacu satu CorrectionRequest ATAU LeaveRequest dan satu User HR.
- Foreign key menggunakan RESTRICT agar akun/profil/permohonan dengan sejarah tidak terhapus.
- AttendanceRecord unik pada employee_id + attendance_date + session.
  Satu baris memuat check-in dan check-out sesi; sesi regular menjadi default.
- Status request pending/approved/rejected terpisah dari present/late/leave/absent.
- Waktu menggunakan timezone Asia/Jakarta, disimpan sebagai datetime.
  requested_at/captured_at berasal dari perangkat pada fase mendatang;
  submitted_at dan reviewed_at harus ditetapkan server.
- Enrollment default not_enrolled; tidak disediakan aksi perubahan sebelum AI nyata tersedia.
- Status, role, dan metadata keputusan tidak menerima mass assignment formulir umum.

## Struktur
app/Enums, Models, Policies, Contracts, Services,
Http/Controllers/Auth, Http/Requests, Http/Middleware;
database/migrations dan seeders; resources/views/components dan auth;
tests/Feature.

## Rencana Phase 2-5
2. Admin (selesai Phase 2): dashboard, CRUD akun (validasi role, email unik, nonaktifkan akun),
   monitoring profil dan attendance read-only dengan pagination/filter.
3. HR (selesai Phase 3): CRUD profil (akun hanya role employee, satu akun per profil),
   monitoring, halaman enrollment nonaktif, CSV, approval koreksi/izin.
4. Employee: query selalu dibatasi employee_id user, history, form correction
   dengan getUserMedia/geolocation serta penjelasan consent dan alternatif HR;
   form leave dan berkas pendukung privat.
5. UI, browser/mobile QA, error states, tes workflow dan dokumentasi final.

## Aturan service approval Phase 3
- Gate approve/reject wajib, HR aktif, request pending, tidak menilai permohonan sendiri.
- Transaksi database + lockForUpdate request dan attendance; status dicek lagi di dalam lock.
- Satu keputusan saja; log append-only dengan salah satu FK permohonan terisi,
  actor HR, transisi pending ke approved/rejected, timestamp server dan notes.
- Approve koreksi menggunakan unique employee/date/session; check-out tidak mendahului
  check-in; konflik izin dan koreksi jam divalidasi. Sesi hanya regular, tanggal yang sama;
  lintas tengah malam ditolak sampai dukungan shift ditambahkan.
- HR boleh memperbarui attendance hanya melalui service keputusan.
  Admin tidak mempunyai bypass Gate::before.
- Leave start <= end; konflik rentang dicek; approved leave tidak menimpa attendance
  yang sudah memiliki jam masuk tanpa resolusi eksplisit HR.
- Approval memvalidasi MIME foto nyata, maksimum 5 MB, koordinat dan waktu koreksi.
  Form Request upload/pengajuan Employee akan dibuat di Phase 4.
- Bukti tetap di storage/app/private; endpoint download mengotorisasi pemilik/HR
  dan tidak menerima path pengguna secara langsung.
- GPS, foto dan timestamp perangkat bukan bukti identitas terpercaya.
- Tidak ada log keputusan buatan dalam seed Phase 1; contoh request masih pending.

## AI mendatang
FaceRecognitionService terikat ke DisabledFaceRecognitionService: unavailable dan
menolak enroll/verify. Kontrak ini placeholder dan dapat direvisi dengan DTO FastAPI
saat API nyata disepakati. Belum ada HTTP client, credential atau hasil AI simulasi.
Saat integrasi nyata dibuat: timeout, service authentication, idempotency key,
audit, retry terbatas dan validasi respons wajib dirancang.


## Catatan implementasi Phase 3
- ApprovalService mengunci Employee, lalu request dan attendance, dalam transaksi
  dengan retry deadlock maksimal 3. Kunci employee bersama menyerialkan keputusan
  leave/correction termasuk ketika belum ada baris attendance.
- Indeks unik nullable pada kedua FK approval_logs membatasi satu keputusan/request.
  Model log menolak update/delete dan mensyaratkan tepat satu request serta HR aktif.
  Perlindungan model bukan jaminan terhadap perubahan SQL langsung oleh DBA.
- EmployeeProfileService mengunci akun saat linking agar tidak berbenturan dengan
  perubahan role oleh Admin, dan menegakkan satu profil per akun.
- Leave approved membuat baris leave pada sesi regular seluruh hari rentang,
  termasuk akhir pekan. Tidak ada kalender libur/saldo cuti/undo keputusan.
- File privat hanya di prefix corrections/leaves/demo, MIME allowlist, no-store,
  nosniff; path pengguna tidak diterima dari URL.
- Service monitoring dipakai kembali oleh Admin dan HR; CSV menggunakan query
  filter yang sama tanpa batas pagination dan melindungi sel dari formula injection.
- Statistik hari ini hanya menghitung profil aktif unik dengan check-in.
  Tren historis mengikuti status aktif profil saat query, bukan snapshot headcount.
