<x-layout title="Detail Karyawan">
<div class="compact-list">
    <x-list-header title="Detail Karyawan" breadcrumb="Detail Karyawan" :parents="['Karyawan' => route($role.'.employees.index')]" :dashboard="route($role.'.home')">
        @if($role === 'hr')@can('update', $employee)<a class="list-control list-primary" href="{{ route('hr.employees.edit', $employee) }}"><x-icon name="pencil"/>Edit Karyawan</a>@endcan @endif
    </x-list-header>
    <section class="employee-read-card" aria-label="Informasi karyawan">
        <h2>{{ $employee->name }}</h2>
        <dl>
            @foreach([
                'ID Karyawan' => $employee->business_id ?? 'Belum diterbitkan',
                'Nama Lengkap' => $employee->name,
                'NIK' => $employee->employee_number,
                'Tanggal Lahir' => $employee->date_of_birth?->format('d M Y') ?? '-',
                'Alamat Rumah' => $employee->home_address ?? '-',
                'Divisi' => $employee->division?->name ?? '-',
                'Jabatan' => $employee->position,
                'Email' => $employee->email,
                'Nomor Telepon' => $employee->phone ?? '-',
                'Status Karyawan' => $employee->is_active ? 'Active' : 'Inactive',
                'Face Enrollment Status' => $employee->enrollment_status === 'enrolled' ? 'Enrolled' : 'Not Enrolled',
            ] as $label => $value)
            <div><dt>{{ $label }}</dt><dd>{{ $value }}</dd></div>
            @endforeach
        </dl>
    </section>
</div>
</x-layout>
