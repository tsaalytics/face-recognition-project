@props(['employees', 'role'])
<div class="table-panel employee-table-panel" role="region" aria-label="Tabel karyawan, geser untuk melihat semua kolom" tabindex="0">
    <table class="data-table employee-table">
        <thead><tr><th scope="col">ID Karyawan</th><th scope="col">Nama</th><th scope="col">Divisi</th><th scope="col">Email</th><th scope="col">Status</th><th scope="col" class="employee-actions-heading">Aksi</th></tr></thead>
        <tbody>
        @forelse($employees as $employee)
            <tr>
                <td class="employee-business-id">{{ $employee->business_id ?? 'Belum diterbitkan' }}</td>
                <td class="employee-name">{{ $employee->name }}</td>
                <td>{{ $employee->division?->name ?? '-' }}</td>
                <td>{{ $employee->email }}</td>
                <td><span @class(['employee-status', 'employee-status-active' => $employee->is_active, 'employee-status-inactive' => !$employee->is_active])>{{ $employee->is_active ? 'Active' : 'Inactive' }}</span></td>
                <td><div class="employee-row-actions">
                    @can('view', $employee)<x-action-link :href="route($role.'.employees.show', $employee)" :label="'Lihat detail '.$employee->name" icon="eye"/>@endcan
                    @if($role === 'hr')
                        @can('update', $employee)<x-action-link :href="route('hr.employees.edit', $employee)" :label="'Edit profil '.$employee->name" icon="pencil"/>@endcan
                    @endif
                </div></td>
            </tr>
        @empty
            <tr><td colspan="6" class="empty-state">Tidak ada karyawan yang cocok dengan filter.</td></tr>
        @endforelse
        </tbody>
    </table>
</div>
