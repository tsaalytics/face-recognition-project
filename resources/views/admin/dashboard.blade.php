<x-layout title="Dashboard Admin">
<x-dashboard-overview :employees="$employeeCount" :present="$presentCount" :late="$lateCount" :absent="$absentCount" :year="$year" :monthly="$monthly" :notifications="$notifications" />
<p class="dashboard-context">Terdapat {{ $activeEmployeeCount }} karyawan aktif dan {{ $userCount }} akun pengguna. Hadir termasuk terlambat. Belum hadir termasuk izin dan bukan keputusan absen final.</p>
</x-layout>
