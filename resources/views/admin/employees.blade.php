<x-layout title="Direktori Karyawan">
<h1 class="text-3xl font-bold text-purple">Direktori karyawan</h1>
<p class="mt-2 text-sm text-slate-500">Seluruh profil karyawan. Pengelolaan profil merupakan kewenangan HR.</p>
<x-monitor-filters :divisions="$divisions" :reset="route('admin.employees.index')"/>
<div class="table-panel"><table class="data-table"><thead><tr><th>NIK / Nama</th><th>Divisi / Jabatan</th><th>Kontak</th><th>Akun login</th><th>Status</th></tr></thead><tbody>
@forelse($employees as $employee)
<tr><td><p class="text-xs text-magenta">{{ $employee->employee_number }}</p><p class="mt-1 font-medium">{{ $employee->name }}</p></td><td>{{ $employee->division->name }}<p class="mt-1 text-xs text-slate-500">{{ $employee->position }}</p></td><td>{{ $employee->email }}<p class="mt-1 text-xs text-slate-500">{{ $employee->phone ?? '-' }}</p></td><td>{{ $employee->user?->email ?? 'Belum terhubung' }}</td><td><x-status :value="$employee->is_active ? 'active' : 'inactive'"/></td></tr>
@empty<tr><td colspan="5" class="empty-state">Tidak ada karyawan yang cocok dengan filter.</td></tr>@endforelse
</tbody></table></div><div class="mt-5">{{ $employees->links() }}</div>
</x-layout>
