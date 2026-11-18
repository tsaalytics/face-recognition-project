<x-layout :title="$employee->exists ? 'Edit Karyawan' : 'Tambah Karyawan'">
<div class="employee-profile-page">
<header class="profile-page-header"><h1>{{ $employee->exists ? 'Edit Karyawan' : 'Tambah Karyawan' }}</h1><nav class="list-breadcrumb" aria-label="Breadcrumb"><a href="{{ route('hr.home') }}">Dashboard</a><span aria-hidden="true">/</span><a href="{{ route('hr.employees.index') }}">Karyawan</a><span aria-hidden="true">/</span><span aria-current="page">{{ $employee->exists ? 'Edit Karyawan' : 'Tambah Karyawan' }}</span></nav></header>
<div class="profile-card">
<form id="employee-profile" method="POST" action="{{ $employee->exists ? route('hr.employees.update', $employee) : route('hr.employees.store') }}" data-submit-form>
@csrf
<input type="hidden" name="_method" value="{{ $employee->exists ? 'PUT' : 'POST' }}">
<div class="profile-section-heading"><div><h2>Informasi Karyawan</h2><p>Lengkapi data profil! Kolom bertanda (*) wajib diisi.</p></div></div>
<div class="profile-form-grid">
@foreach(['name'=>'Nama Lengkap', 'employee_number'=>'NIK', 'email'=>'Email', 'phone'=>'Nomor Telepon'] as $field=>$label)
<x-form-field :name="$field" :label="$label" :required="true"><input class="field" id="{{ $field }}" name="{{ $field }}" value="{{ old($field, $employee->$field) }}" type="{{ $field === 'email' ? 'email' : ($field === 'phone' ? 'tel' : 'text') }}" maxlength="{{ $field === 'employee_number' ? 50 : ($field === 'phone' ? 30 : 255) }}" required @error($field) aria-invalid="true" aria-describedby="{{ $field }}-error" @enderror></x-form-field>
@endforeach
<x-form-field name="date_of_birth" label="Tanggal Lahir" :required="true"><input class="field" type="date" id="date_of_birth" name="date_of_birth" value="{{ old('date_of_birth', $employee->date_of_birth?->format('Y-m-d')) }}" max="{{ today()->toDateString() }}" required @error('date_of_birth') aria-invalid="true" aria-describedby="date_of_birth-error" @enderror></x-form-field>
<x-form-field name="division_id" label="Divisi" :required="true"><select id="division_id" name="division_id" class="field" required @error('division_id') aria-invalid="true" aria-describedby="division_id-error" @enderror><option value="">Pilih divisi</option>@foreach($divisions as $division)<option value="{{ $division->id }}" @selected((string) old('division_id', $employee->division_id) === (string) $division->id)>{{ $division->name }}</option>@endforeach</select></x-form-field>
<x-form-field name="position" label="Jabatan" :required="true"><input class="field" id="position" name="position" value="{{ old('position', $employee->position) }}" maxlength="255" required @error('position') aria-invalid="true" aria-describedby="position-error" @enderror></x-form-field>
<x-form-field name="is_active" label="Status Karyawan" :required="true"><select id="is_active" name="is_active" class="field" required><option value="1" @selected((string) old('is_active', $employee->exists ? (int) $employee->is_active : 1) === '1')>Aktif</option><option value="0" @selected((string) old('is_active', $employee->exists ? (int) $employee->is_active : 1) === '0')>Nonaktif</option></select></x-form-field>
<x-form-field name="home_address" label="Alamat Rumah" :required="true" :wide="true"><textarea class="field" id="home_address" name="home_address" required maxlength="3000" rows="3" @error('home_address') aria-invalid="true" aria-describedby="home_address-error" @enderror>{{ old('home_address', $employee->home_address) }}</textarea></x-form-field>
</div>
</form>
<p data-profile-status role="status" class="profile-save-status"></p>
<x-face-enrollment-section :employee="$employee" :sample-count="$sampleCount" :enrolled-at="$enrolledAt" :audits="$enrollmentAudits"/>
<div class="profile-form-actions"><a href="{{ route('hr.employees.index') }}" class="list-control">Batal</a><button type="submit" form="employee-profile" class="list-control list-primary" data-profile-save>Simpan Profil</button></div>
</div>
</div>
</x-layout>
