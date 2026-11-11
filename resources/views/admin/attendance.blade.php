<x-layout title="Riwayat Absensi">
<div class="flex flex-wrap items-center justify-between gap-3"><div><h1 class="text-3xl font-bold text-purple">Riwayat absensi</h1><p class="mt-2 text-sm text-slate-500">Pemantauan kehadiran seluruh karyawan. Waktu ditampilkan dalam Asia/Jakarta.</p></div><span class="rounded-full bg-white px-4 py-2 text-xs font-semibold text-purple">Akses baca saja</span></div>
<x-monitor-filters :divisions="$divisions" :attendance="true" :reset="route('admin.attendance.index')"/>
<div class="table-panel"><table class="data-table"><thead><tr><th>Tanggal / Sesi</th><th>Karyawan</th><th>Divisi</th><th>Masuk</th><th>Pulang</th><th>Status</th><th>Sumber</th></tr></thead><tbody>
@forelse($records as $record)
<tr><td>{{ $record->attendance_date->format('d M Y') }}<p class="mt-1 text-xs text-slate-500">{{ $record->session }}</p></td><td>{{ $record->employee->name }}<p class="mt-1 text-xs text-slate-500">{{ $record->employee->employee_number }}</p></td><td>{{ $record->employee->division->name }}</td><td class="whitespace-nowrap">{{ $record->check_in_at?->format('H:i') ?? '-' }}</td><td class="whitespace-nowrap">{{ $record->check_out_at?->format('H:i') ?? '-' }}</td><td><x-status :value="$record->status"/></td><td>{{ ['seed_fixture'=>'Data demo sintetis','hr_approved'=>'HR Approved'][$record->source] ?? $record->source }}</td></tr>
@empty<tr><td colspan="7" class="empty-state">Tidak ada catatan absensi yang cocok dengan filter.</td></tr>@endforelse
</tbody></table></div><div class="mt-5">{{ $records->links() }}</div>
</x-layout>
