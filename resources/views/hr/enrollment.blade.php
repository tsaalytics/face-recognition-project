<x-layout title="Face Enrollment">
<div class="compact-list">
<x-list-toolbar title="Face Enrollment" breadcrumb="Face Enrollment" :dashboard="route('hr.home')" :action="route('hr.enrollment')" :paginator="$employees" :filters="['q', 'division_id', 'active']" unit="karyawan" description="Pilih karyawan untuk Register Face atau kelola enrollment.">
<x-directory-filter-fields :divisions="$divisions"/>
</x-list-toolbar>
<p class="list-info-note">Khusus HR: Register Face, Re-enroll, atau Delete Enrollment memerlukan persetujuan. Foto tidak disimpan.</p>
<div class="table-panel" role="region" aria-label="Tabel data, geser untuk melihat semua kolom" tabindex="0"><table class="data-table"><thead><tr><th scope="col">Karyawan</th><th scope="col">Divisi</th><th scope="col">Status enrollment</th></tr></thead><tbody>
@forelse($employees as $employee)<tr><td>{{ $employee->name }}<p class="text-xs text-slate-500">{{ $employee->employee_number }} / ID: {{ $employee->business_id ?? 'Belum diterbitkan' }}</p></td><td>{{ $employee->division->name }}</td><td><span class="rounded-full bg-lavender px-3 py-1 text-xs text-purple">{{ $employee->enrollment_status==='enrolled' ? 'Enrolled' : 'Not Enrolled' }}</span><a class="reset-link mt-3 block" href="{{ route('hr.faces.show', $employee) }}">{{ $employee->enrollment_status === 'enrolled' ? 'Kelola / Re-enroll Face' : 'Register Face' }}</a></td></tr>
@empty<tr><td colspan="3" class="empty-state">Tidak ada karyawan yang cocok.</td></tr>@endforelse
</tbody></table></div>
<x-list-pagination :paginator="$employees"/>
</div>
</x-layout>
