@props(['records', 'divisions', 'role'])
@php
    $filterKeys = ['q', 'division_id', 'from', 'to', 'status'];
@endphp
<div class="compact-list">
<x-list-toolbar title="Riwayat Absensi" breadcrumb="Riwayat Absensi" :dashboard="route($role.'.home')" :action="route($role.'.attendance.index')" :paginator="$records" :filters="$filterKeys">
    <x-slot:actions>
        @if($role === 'hr')
        <a class="list-control list-primary" href="{{ route('hr.attendance.export', request()->only($filterKeys)) }}" title="Download seluruh data sesuai filter aktif"><x-icon name="download"/>Download CSV</a>
        @endif
    </x-slot:actions>
    <x-directory-filter-fields :divisions="$divisions" :attendance="true"/>
</x-list-toolbar>
<div class="table-panel" role="region" aria-label="Tabel data, geser untuk melihat semua kolom" tabindex="0"><table class="data-table"><thead><tr><th scope="col">Tanggal</th><th scope="col">ID Karyawan</th><th scope="col">Nama Karyawan</th><th scope="col">Divisi</th><th scope="col">Masuk</th><th scope="col">Pulang</th><th scope="col">Status</th><th scope="col">Sumber</th></tr></thead><tbody>
@forelse($records as $record)
<tr><td class="whitespace-nowrap">{{ $record->attendance_date->format('d M Y') }}</td><td class="whitespace-nowrap">{{ $record->employee->business_id ?? 'Belum diterbitkan' }}</td><td>{{ $record->employee->name }}</td><td>{{ $record->employee->division->name }}</td><td class="whitespace-nowrap">{{ $record->check_in_at?->format('H:i') ?? '-' }}</td><td class="whitespace-nowrap">{{ $record->check_out_at?->format('H:i') ?? '-' }}</td><td><x-status :value="$record->status"/></td><td>{{ ['seed_fixture'=>'Data demo sintetis','hr_approved'=>'HR Approved','hr_approved_correction'=>'HR Approved Correction'][$record->source] ?? $record->source }}</td></tr>
@empty<tr><td colspan="8" class="empty-state">Tidak ada catatan absensi yang cocok dengan filter.</td></tr>@endforelse
</tbody></table></div>
<x-list-pagination :paginator="$records"/>
</div>
