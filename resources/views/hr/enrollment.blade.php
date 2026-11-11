<x-layout title="Face Enrollment">
<h1 class="text-3xl font-bold text-purple">Face enrollment</h1>
<div class="mt-5 rounded-xl border border-purple/10 bg-white p-6"><span class="rounded-full bg-lavender px-3 py-1 text-xs font-semibold text-purple">AI belum diaktifkan</span><p class="mt-4 text-sm leading-6 text-slate-600">Halaman ini mempersiapkan pemantauan enrollment. Belum ada pengambilan wajah, penyimpanan embedding, koneksi Python, atau aksi enrollment. Status tidak dapat diubah melalui halaman ini.</p></div>
<x-monitor-filters :divisions="$divisions" :reset="route('hr.enrollment')"/>
<div class="table-panel"><table class="data-table"><thead><tr><th>Karyawan</th><th>Divisi</th><th>Status enrollment</th></tr></thead><tbody>
@forelse($employees as $employee)<tr><td>{{ $employee->name }}<p class="text-xs text-slate-500">{{ $employee->employee_number }}</p></td><td>{{ $employee->division->name }}</td><td><span class="rounded-full bg-lavender px-3 py-1 text-xs text-purple">{{ $employee->enrollment_status==='enrolled' ? 'Enrolled' : 'Not Enrolled' }}</span></td></tr>
@empty<tr><td colspan="3" class="empty-state">Tidak ada karyawan yang cocok.</td></tr>@endforelse
</tbody></table></div><div class="mt-5">{{ $employees->links() }}</div>
</x-layout>
