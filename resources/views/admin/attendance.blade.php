<x-layout title="Riwayat Absensi">
<div class="compact-list">
<x-list-toolbar title="Riwayat Absensi" breadcrumb="Riwayat Absensi" :dashboard="route('admin.home')" :action="route('admin.attendance.index')" :paginator="$records" :filters="['q', 'division_id', 'from', 'to', 'status']" unit="catatan absensi" description="Akses baca saja / Asia/Jakarta">
<x-directory-filter-fields :divisions="$divisions" :attendance="true"/>
</x-list-toolbar>
<div class="table-panel" role="region" aria-label="Tabel data, geser untuk melihat semua kolom" tabindex="0"><table class="data-table"><thead><tr><th scope="col">Tanggal / Sesi</th><th scope="col">Karyawan</th><th scope="col">Divisi</th><th scope="col">Masuk</th><th scope="col">Pulang</th><th scope="col">Status</th><th scope="col">Sumber</th></tr></thead><tbody>
@forelse($records as $record)
<tr><td>{{ $record->attendance_date->format('d M Y') }}<p class="mt-1 text-xs text-slate-500">{{ $record->session }}</p></td><td>{{ $record->employee->name }}<p class="mt-1 text-xs text-slate-500">{{ $record->employee->employee_number }}</p></td><td>{{ $record->employee->division->name }}</td><td class="whitespace-nowrap">{{ $record->check_in_at?->format('H:i') ?? '-' }}</td><td class="whitespace-nowrap">{{ $record->check_out_at?->format('H:i') ?? '-' }}</td><td><x-status :value="$record->status"/></td><td>{{ ['seed_fixture'=>'Data demo sintetis','hr_approved'=>'HR Approved','hr_approved_correction'=>'HR Approved Correction'][$record->source] ?? $record->source }}</td></tr>
@empty<tr><td colspan="7" class="empty-state">Tidak ada catatan absensi yang cocok dengan filter.</td></tr>@endforelse
</tbody></table></div>
<x-list-pagination :paginator="$records"/>
</div>
</x-layout>
