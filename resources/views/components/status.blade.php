@props(['value'])
@php
$labels=['pending'=>'Pending','approved'=>'Approved','rejected'=>'Rejected','present'=>'Hadir','late'=>'Terlambat','leave'=>'Izin','absent'=>'Tidak hadir','active'=>'Aktif','inactive'=>'Nonaktif'];
$tone=match($value) {
 'present','active','approved'=>'bg-emerald-50 text-emerald-800',
 'late','pending'=>'bg-amber-50 text-amber-800',
 'rejected','absent','inactive'=>'bg-rose-50 text-rose-800',
 default=>'bg-lavender text-purple',
};
@endphp
<span class="inline-flex whitespace-nowrap rounded-full px-3 py-1 text-xs font-semibold {{ $tone }}">{{ $labels[$value] ?? $value }}</span>
