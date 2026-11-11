<x-layout title="Dashboard Admin">
<p class="text-xs font-bold uppercase tracking-widest text-magenta">Ringkasan hari ini / {{ today()->format('d M Y') }}</p>
<h1 class="mt-3 text-3xl font-bold text-purple">Pantau kehadiran tim Anda</h1>
<p class="mt-3 text-sm text-slate-500">Ringkasan seluruh karyawan dan akun pengguna perusahaan.</p>
<div class="mt-8 grid gap-5 sm:grid-cols-2 xl:grid-cols-4">
@foreach(['Total karyawan'=>$employeeCount,'Hadir hari ini'=>$presentCount,'Belum hadir hari ini'=>$absentCount,'Total akun pengguna'=>$userCount] as $label=>$count)
<section class="rounded-2xl border border-slate-100 bg-white p-6 shadow-sm"><p class="text-sm text-slate-500">{{ $label }}</p><p class="mt-4 text-4xl font-bold text-purple">{{ number_format($count) }}</p></section>
@endforeach
</div>
<p class="mt-4 text-xs leading-5 text-slate-500">Terdapat {{ $activeEmployeeCount }} karyawan aktif. Hadir mencakup status hadir/terlambat dengan check-in, dihitung satu kali per karyawan aktif. Belum hadir mencakup seluruh karyawan aktif yang belum check-in, termasuk yang izin; bukan keputusan absen final. Waktu: Asia/Jakarta.</p>
<div class="mt-8 grid gap-5 lg:grid-cols-3">
@foreach([['admin.users.index','Kelola akun','Buat, perbarui, dan nonaktifkan akun HR atau Employee.'],['admin.employees.index','Direktori karyawan','Lihat profil karyawan dan divisi yang terhubung.'],['admin.attendance.index','Riwayat absensi','Telusuri kehadiran berdasarkan tanggal, divisi, atau nama.']] as [$route,$heading,$description])
<a href="{{ route($route) }}" class="rounded-2xl border border-slate-100 bg-white p-6 shadow-sm transition hover:border-magenta"><h2 class="font-semibold text-purple">{{ $heading }} <span aria-hidden="true">&rarr;</span></h2><p class="mt-3 text-sm leading-6 text-slate-500">{{ $description }}</p></a>
@endforeach
</div>
<div class="mt-8 rounded-xl border border-purple/10 bg-white p-5 text-sm leading-6 text-slate-600">Admin memiliki akses baca untuk absensi. Persetujuan koreksi dan izin merupakan kewenangan HR. AI belum diaktifkan.</div>
</x-layout>
