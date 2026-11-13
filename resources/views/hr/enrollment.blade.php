<x-layout title="Face Enrollment">
<h1 class="text-3xl font-bold text-purple">Face enrollment</h1>
<div class="mt-5 rounded-xl border border-purple/10 bg-white p-6"><p class="text-sm leading-6 text-slate-600">Khusus HR: pilih karyawan untuk Register Face, Re-enroll, atau Delete Enrollment. Memerlukan persetujuan; foto tidak disimpan. Absensi wajah belum diaktifkan.</p></div>
<x-monitor-filters :divisions="$divisions" :reset="route('hr.enrollment')"/>
<div class="table-panel" role="region" aria-label="Tabel data, geser untuk melihat semua kolom" tabindex="0"><table class="data-table"><thead><tr><th scope="col">Karyawan</th><th scope="col">Divisi</th><th scope="col">Status enrollment</th></tr></thead><tbody>
@forelse($employees as $employee)<tr><td>{{ $employee->name }}<p class="text-xs text-slate-500">{{ $employee->employee_number }} / ID: {{ $employee->business_id ?? 'Belum diterbitkan' }}</p></td><td>{{ $employee->division->name }}</td><td><span class="rounded-full bg-lavender px-3 py-1 text-xs text-purple">{{ $employee->enrollment_status==='enrolled' ? 'Enrolled' : 'Not Enrolled' }}</span><a class="reset-link mt-3 block" href="{{ route('hr.faces.show', $employee) }}">{{ $employee->enrollment_status === 'enrolled' ? 'Kelola / Re-enroll Face' : 'Register Face' }}</a></td></tr>
@empty<tr><td colspan="3" class="empty-state">Tidak ada karyawan yang cocok.</td></tr>@endforelse
</tbody></table></div><div class="mt-5">{{ $employees->links() }}</div>
</x-layout>
