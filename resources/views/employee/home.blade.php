<x-layout title="Workspace Saya">
<p class="text-xs font-bold uppercase tracking-widest text-magenta">Employee workspace</p>
<h1 class="mt-3 text-3xl font-bold text-purple">Selamat datang, {{ auth()->user()->name }}.</h1>
@if(!$employee)<div role="status" class="mt-6 rounded-xl border border-amber-200 bg-amber-50 p-5 text-sm text-amber-900">Akun Anda belum terhubung ke profil karyawan. Hubungi HR. Riwayat akan kosong dan pengajuan belum dapat dibuat.</div>
@elseif(!$employee->is_active)<div role="status" class="mt-6 rounded-xl border border-amber-200 bg-amber-50 p-5 text-sm text-amber-900">Profil karyawan nonaktif. Anda dapat melihat riwayat sendiri; hubungi HR untuk pengajuan baru.</div>
@else<p class="mt-3 text-sm text-slate-500">{{ $employee->employee_number }} / {{ $employee->division->name }} / {{ $employee->position }}</p>@endif
<div class="mt-8 grid gap-5 lg:grid-cols-3">
@foreach([['employee.attendance.index','Absensi saya','Lihat jam masuk, jam pulang, dan status kehadiran.'],['employee.corrections.index','Koreksi absensi','Ajukan koreksi dengan bukti kamera dan lokasi untuk ditinjau HR.'],['employee.leaves.index','Izin, sakit, dan cuti','Ajukan izin dan pantau keputusan HR.']] as [$route,$title,$description])
<a href="{{ route($route) }}" class="rounded-2xl border border-slate-100 bg-white p-6 shadow-sm hover:border-magenta"><h2 class="font-semibold text-purple">{{ $title }} &rarr;</h2><p class="mt-3 text-sm leading-6 text-slate-500">{{ $description }}</p></a>@endforeach
</div>
<p class="mt-7 text-sm leading-6 text-slate-500">AI belum diaktifkan. Pengajuan koreksi tidak langsung mencatat kehadiran; keputusan akhir berada pada HR.</p>
</x-layout>
