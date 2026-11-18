<x-layout :title="$kind === 'corrections' ? 'Approval Koreksi' : 'Pengajuan Izin'">
<div class="compact-list">
<x-list-toolbar :title="$kind === 'corrections' ? 'Permohonan Koreksi Absensi' : 'Pengajuan Izin, Sakit, dan Cuti'" :breadcrumb="$kind === 'corrections' ? 'Approval Koreksi' : 'Pengajuan Izin'" :dashboard="route('hr.home')" :action="route('hr.'.$kind.'.index')" :paginator="$requests" :filters="['q', 'status']" >
<x-request-filter-fields :search="true"/>
</x-list-toolbar>
<div class="table-panel" role="region" aria-label="Tabel data, geser untuk melihat semua kolom" tabindex="0"><table class="data-table"><thead><tr><th scope="col">Karyawan</th><th scope="col">Tanggal</th><th scope="col">Jenis</th><th scope="col">Status</th><th scope="col">Detail</th></tr></thead><tbody>
@forelse($requests as $item)<tr><td>{{ $item->employee->name }}<p class="text-xs text-slate-500"></p></td><td>{{ $kind==='corrections' ? $item->attendance_date->format('d M Y') : $item->start_date->format('d M Y').' - '.$item->end_date->format('d M Y') }}</td><td>{{ ['check_in'=>'Check In','check_out'=>'Check Out','permission'=>'Izin','sick'=>'Sakit','annual'=>'Cuti'][$item->type] ?? $item->type }}</td><td><x-status :value="$item->status"/></td><td><a class="reset-link" href="{{ route('hr.'.$kind.'.show',$item) }}">Tinjau</a></td></tr>
@empty<tr><td colspan="5" class="empty-state">Tidak ada permohonan yang cocok.</td></tr>@endforelse
</tbody></table></div>
<x-list-pagination :paginator="$requests"/>
</div>
</x-layout>
