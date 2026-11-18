<x-layout title="Absensi Saya">
<div class="compact-list">
<x-list-toolbar title="Riwayat Absensi Saya" breadcrumb="Absensi Saya" :dashboard="route('employee.home')" :action="route('employee.attendance.index')" :paginator="$records" :filters="['from', 'to']" unit="catatan absensi">
<div class="list-filter-date-range"><div><label for="from">Dari tanggal</label><input class="field" id="from" name="from" type="date" value="{{ request('from') }}"></div><div><label for="to">Sampai tanggal</label><input class="field" id="to" name="to" type="date" value="{{ request('to') }}"></div></div>
</x-list-toolbar>
<div class="table-panel" role="region" aria-label="Tabel data, geser untuk melihat semua kolom" tabindex="0"><table class="data-table"><thead><tr><th scope="col">Tanggal / Sesi</th><th scope="col">Masuk</th><th scope="col">Pulang</th><th scope="col">Status</th><th scope="col">Sumber</th></tr></thead><tbody>
@forelse($records as $record)<tr><td>{{ $record->attendance_date->format('d M Y') }}<p class="text-xs text-slate-500">{{ $record->session }}</p></td><td>{{ $record->check_in_at?->format('H:i') ?? '-' }}</td><td>{{ $record->check_out_at?->format('H:i') ?? '-' }}</td><td><x-status :value="$record->status"/></td><td>{{ $record->source==='seed_fixture' ? 'Data demo sintetis' : 'HR Approved' }}</td></tr>
@empty<tr><td colspan="5" class="empty-state">Belum ada catatan absensi dalam rentang ini.</td></tr>@endforelse
</tbody></table></div>
<x-list-pagination :paginator="$records"/>
</div>
</x-layout>
