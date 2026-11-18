<x-layout title="Riwayat Absensi">
@php
    $filterKeys = ['q', 'division_id', 'from', 'to', 'status'];
    $activeFilters = collect(request()->only($filterKeys))->filter(fn ($value) => is_scalar($value) && (string) $value !== '')->count();
@endphp
<div class="compact-list">
<x-list-header title="Riwayat Absensi" breadcrumb="Riwayat Absensi" :dashboard="route('hr.home')">
    <x-per-page :paginator="$records" :action="route('hr.attendance.index')" :filters="$filterKeys"/>
    <x-list-filter :active="$activeFilters" :action="route('hr.attendance.index')" :reset="route('hr.attendance.index', ['per_page' => $records->perPage()])" :per-page="$records->perPage()">
        <div><label for="q">Nama / NIK</label><input id="q" name="q" class="field" value="{{ request('q') }}" placeholder="Cari karyawan…"></div>
        <div><label for="division_id">Divisi</label><select id="division_id" name="division_id" class="field"><option value="">Semua divisi</option>@foreach($divisions as $division)<option value="{{ $division->id }}" @selected((string) request('division_id') === (string) $division->id)>{{ $division->name }}</option>@endforeach</select></div>
        <div class="list-filter-date-range">
            <div><label for="from">Dari tanggal</label><input id="from" name="from" type="date" class="field" value="{{ request('from') }}"></div>
            <div><label for="to">Sampai tanggal</label><input id="to" name="to" type="date" class="field" value="{{ request('to') }}"></div>
        </div>
        <div><label for="status">Status kehadiran</label><select id="status" name="status" class="field"><option value="">Semua status</option>@foreach(['present'=>'Hadir', 'late'=>'Terlambat', 'leave'=>'Izin', 'absent'=>'Tidak hadir'] as $value=>$label)<option value="{{ $value }}" @selected(request('status') === $value)>{{ $label }}</option>@endforeach</select></div>
    </x-list-filter>
    <a class="list-control list-primary" href="{{ route('hr.attendance.export', request()->only($filterKeys)) }}" title="Download seluruh data sesuai filter aktif"><x-icon name="download"/>Download CSV</a>
</x-list-header>
<div class="list-secondary-bar">

</div>
<div class="table-panel" role="region" aria-label="Tabel data, geser untuk melihat semua kolom" tabindex="0"><table class="data-table"><thead><tr><th scope="col">Tanggal</th><th scope="col">ID Karyawan</th><th scope="col">Nama Karyawan</th><th scope="col">Divisi</th><th scope="col">Masuk</th><th scope="col">Pulang</th><th scope="col">Status</th><th scope="col">Sumber</th></tr></thead><tbody>
@forelse($records as $record)
<tr><td class="whitespace-nowrap">{{ $record->attendance_date->format('d M Y') }}</td><td class="whitespace-nowrap">{{ $record->employee->business_id ?? 'Belum diterbitkan' }}</td><td>{{ $record->employee->name }}</td><td>{{ $record->employee->division->name }}</td><td class="whitespace-nowrap">{{ $record->check_in_at?->format('H:i') ?? '-' }}</td><td class="whitespace-nowrap">{{ $record->check_out_at?->format('H:i') ?? '-' }}</td><td><x-status :value="$record->status"/></td><td>{{ ['seed_fixture'=>'Data demo sintetis','hr_approved'=>'HR Approved','hr_approved_correction'=>'HR Approved Correction'][$record->source] ?? $record->source }}</td></tr>
@empty<tr><td colspan="8" class="empty-state">Tidak ada catatan absensi yang cocok dengan filter.</td></tr>@endforelse
</tbody></table></div>
<x-list-pagination :paginator="$records"/>
</div>
</x-layout>
