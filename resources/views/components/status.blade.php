@props(['value'])
@php
$labels=['pending'=>'Pending','approved'=>'Approved','rejected'=>'Rejected','present'=>'Hadir','late'=>'Terlambat','leave'=>'Izin','absent'=>'Tidak hadir','active'=>'Aktif','inactive'=>'Nonaktif'];
$positive=in_array($value,['present','active']);
@endphp
<span @class(['inline-flex whitespace-nowrap rounded-full px-3 py-1 text-xs font-semibold','bg-emerald-50 text-emerald-800'=>$positive,'bg-amber-50 text-amber-800'=>$value==='late','bg-lavender text-purple'=>!$positive && $value!=='late'])>{{ $labels[$value] ?? $value }}</span>
