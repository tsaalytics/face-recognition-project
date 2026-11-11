<x-layout title="Dashboard HR">
<p class="text-xs font-bold uppercase tracking-widest text-magenta">{{ today()->format('d M Y') }} / Asia/Jakarta</p>
<h1 class="mt-3 text-3xl font-bold text-purple">Kehadiran dan permohonan tim</h1>
<div class="mt-7 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
@foreach(['Karyawan aktif'=>$active,'Hadir hari ini'=>$present,'Terlambat'=>$late,'Belum hadir'=>$absent] as $label=>$count)
<section class="rounded-2xl bg-white p-6 shadow-sm"><p class="text-sm text-slate-500">{{ $label }}</p><p class="mt-4 text-4xl font-bold text-purple">{{ $count }}</p></section>
@endforeach
</div>
<p class="mt-3 text-xs leading-5 text-slate-500">Hadir termasuk terlambat dan dihitung sekali per karyawan aktif. Belum hadir termasuk izin dan bukan keputusan absen final.</p>
<div class="mt-6 grid gap-4 sm:grid-cols-2">
<a href="{{ route('hr.corrections.index',['status'=>'pending']) }}" class="rounded-2xl border border-magenta/15 bg-white p-6"><span class="text-2xl font-bold text-magenta">{{ $pendingCorrections }}</span><h2 class="mt-2 font-semibold text-purple">Koreksi menunggu keputusan &rarr;</h2></a>
<a href="{{ route('hr.leaves.index',['status'=>'pending']) }}" class="rounded-2xl border border-magenta/15 bg-white p-6"><span class="text-2xl font-bold text-magenta">{{ $pendingLeaves }}</span><h2 class="mt-2 font-semibold text-purple">Izin menunggu keputusan &rarr;</h2></a>
</div>
<div class="mt-6 grid gap-5 xl:grid-cols-2">
<section class="rounded-2xl bg-white p-6"><h2 class="font-semibold text-purple">Tren kehadiran 7 hari</h2><p class="mt-2 text-xs text-slate-500">Jumlah karyawan aktif unik dengan check-in. Data historis mengikuti status aktif saat ini.</p>
<div class="mt-6 space-y-3" role="img" aria-label="Tren kehadiran tujuh hari">@foreach($trend as $day)<div class="flex items-center gap-3 text-xs"><span class="w-10">{{ $day['label'] }}</span><div class="h-4 flex-1 rounded bg-lavender"><div class="h-4 rounded bg-magenta" style="width: {{ max(0,min(100,$active ? $day['count']/$active*100 : 0)) }}%"></div></div><span class="w-7 text-right">{{ $day['count'] }}</span></div>@endforeach</div>
</section>
<section class="rounded-2xl bg-white p-6"><h2 class="font-semibold text-purple">Kehadiran per divisi hari ini</h2><div class="mt-5 space-y-4">@forelse($divisions as $division)<div><div class="flex justify-between gap-3 text-sm"><span>{{ $division->name }}</span><span>{{ $division->present_count }}/{{ $division->active_count }}</span></div><div class="mt-2 h-2 rounded bg-lavender"><div class="h-2 rounded bg-purple" style="width: {{ $division->active_count ? $division->present_count/$division->active_count*100 : 0 }}%"></div></div></div>@empty<p class="text-sm text-slate-500">Belum ada divisi.</p>@endforelse</div></section>
</div>
<p class="mt-6 text-xs text-slate-500">AI belum diaktifkan. Data demo tetap sintetis.</p>
</x-layout>
