<x-layout title="Absensi Saya">
<h1 class="text-3xl font-bold text-purple">Riwayat absensi saya</h1>
<p class="mt-2 text-sm text-slate-500">Hanya catatan milik Anda. Waktu menggunakan Asia/Jakarta. Hari tanpa catatan tidak otomatis ditandai tidak hadir.</p>
<form method="GET" class="filter-panel"><div><label class="filter-label" for="from">Dari tanggal</label><input class="field" id="from" name="from" type="date" value="{{ request('from') }}"></div><div><label class="filter-label" for="to">Sampai tanggal</label><input class="field" id="to" name="to" type="date" value="{{ request('to') }}"></div><button class="button">Terapkan</button><a class="reset-link" href="{{ route('employee.attendance.index') }}">Reset</a></form>
<div class="table-panel"><table class="data-table"><thead><tr><th>Tanggal / Sesi</th><th>Masuk</th><th>Pulang</th><th>Status</th><th>Sumber</th></tr></thead><tbody>
@forelse($records as $record)<tr><td>{{ $record->attendance_date->format('d M Y') }}<p class="text-xs text-slate-500">{{ $record->session }}</p></td><td>{{ $record->check_in_at?->format('H:i') ?? '-' }}</td><td>{{ $record->check_out_at?->format('H:i') ?? '-' }}</td><td><x-status :value="$record->status"/></td><td>{{ $record->source==='seed_fixture' ? 'Data demo sintetis' : 'HR Approved' }}</td></tr>
@empty<tr><td colspan="5" class="empty-state">Belum ada catatan absensi dalam rentang ini.</td></tr>@endforelse
</tbody></table></div><div class="mt-5">{{ $records->links() }}</div>
</x-layout>
