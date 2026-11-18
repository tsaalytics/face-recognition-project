<x-layout :title="$kind === 'corrections' ? 'Koreksi Saya' : 'Izin Saya'">
<div class="compact-list">
<x-list-toolbar :title="$kind === 'corrections' ? 'Permohonan Koreksi Saya' : 'Pengajuan Izin Saya'" :breadcrumb="$kind === 'corrections' ? 'Koreksi Saya' : 'Izin Saya'" :dashboard="route('employee.home')" :action="route('employee.'.$kind.'.index')" :paginator="$requests" :filters="['status']" unit="permohonan" description="Pantau status dan riwayat keputusan HR.">
<x-slot:actions>
@can('create', $kind === 'corrections' ? \App\Models\AttendanceCorrectionRequest::class : \App\Models\LeaveRequest::class)
<a class="list-control list-primary" href="{{ route('employee.'.$kind.'.create') }}"><x-icon name="plus"/>Ajukan {{ $kind === 'corrections' ? 'koreksi' : 'izin' }}</a>
@endcan
</x-slot:actions>
<x-request-filter-fields/>
</x-list-toolbar>
@if(!$employee?->is_active)<p class="list-info-note">Pengajuan belum tersedia. Hubungi HR untuk menghubungkan atau mengaktifkan profil Anda.</p>@endif
<div class="table-panel" role="region" aria-label="Tabel data, geser untuk melihat semua kolom" tabindex="0"><table class="data-table"><thead><tr><th scope="col">Tanggal</th><th scope="col">Jenis</th><th scope="col">Diajukan</th><th scope="col">Status</th><th scope="col">Detail</th></tr></thead><tbody>
@forelse($requests as $item)<tr><td>{{ $kind==='corrections' ? $item->attendance_date->format('d M Y') : $item->start_date->format('d M Y').' - '.$item->end_date->format('d M Y') }}</td><td>{{ ['check_in'=>'Check In','check_out'=>'Check Out','permission'=>'Izin','sick'=>'Sakit','annual'=>'Cuti'][$item->type] ?? $item->type }}</td><td>{{ $item->submitted_at->format('d M Y H:i') }}</td><td><x-status :value="$item->status"/></td><td><a class="reset-link" href="{{ route('employee.'.$kind.'.show',$item) }}">Lihat detail</a></td></tr>
@empty<tr><td colspan="5" class="empty-state">Belum ada permohonan yang cocok.</td></tr>@endforelse
</tbody></table></div>
<x-list-pagination :paginator="$requests"/>
</div>
</x-layout>
