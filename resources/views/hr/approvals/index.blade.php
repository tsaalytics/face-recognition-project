<x-layout :title="$kind==='corrections' ? 'Approval Koreksi' : 'Pengajuan Izin'">
<h1 class="text-3xl font-bold text-purple">{{ $kind==='corrections' ? 'Permohonan koreksi absensi' : 'Pengajuan izin, sakit, dan cuti' }}</h1>
<p class="mt-2 text-sm text-slate-500">Tinjau bukti dan alasan sebelum memberikan keputusan. Riwayat permohonan tetap tersimpan.</p>
<form method="GET" class="filter-panel">
<div class="grow"><label for="q" class="filter-label">Nama / NIK</label><input class="field" id="q" name="q" value="{{ request('q') }}"></div>
<div><label for="status" class="filter-label">Status</label><select class="field" id="status" name="status"><option value="">Semua status</option>@foreach(['pending'=>'Pending','approved'=>'Approved','rejected'=>'Rejected'] as $value=>$label)<option value="{{ $value }}" @selected(request('status')===$value)>{{ $label }}</option>@endforeach</select></div>
<button class="button">Terapkan</button><a class="reset-link" href="{{ route('hr.'.$kind.'.index') }}">Reset</a>
</form>
<div class="table-panel" role="region" aria-label="Tabel data, geser untuk melihat semua kolom" tabindex="0"><table class="data-table"><thead><tr><th scope="col">Karyawan</th><th scope="col">Tanggal</th><th scope="col">Jenis</th><th scope="col">Status</th><th scope="col">Detail</th></tr></thead><tbody>
@forelse($requests as $item)<tr><td>{{ $item->employee->name }}<p class="text-xs text-slate-500">{{ $item->employee->employee_number }} / {{ $item->employee->division->name }}</p></td><td>{{ $kind==='corrections' ? $item->attendance_date->format('d M Y') : $item->start_date->format('d M Y').' - '.$item->end_date->format('d M Y') }}</td><td>{{ ['check_in'=>'Check In','check_out'=>'Check Out','permission'=>'Izin','sick'=>'Sakit','annual'=>'Cuti'][$item->type] ?? $item->type }}</td><td><x-status :value="$item->status"/></td><td><a class="reset-link" href="{{ route('hr.'.$kind.'.show',$item) }}">Tinjau</a></td></tr>
@empty<tr><td colspan="5" class="empty-state">Tidak ada permohonan yang cocok.</td></tr>@endforelse
</tbody></table></div><div class="mt-5">{{ $requests->links() }}</div>
</x-layout>
