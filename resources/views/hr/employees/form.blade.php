<x-layout :title="$employee->exists ? 'Edit Profil' : 'Tambah Karyawan'">
<a href="{{ route('hr.employees.index') }}" class="reset-link">&larr; Kembali ke direktori</a>
<h1 class="mt-4 text-3xl font-bold text-purple">{{ $employee->exists ? 'Edit profil karyawan' : 'Tambah karyawan' }}</h1>
<form method="POST" action="{{ $employee->exists ? route('hr.employees.update',$employee) : route('hr.employees.store') }}" class="mt-7 max-w-4xl rounded-2xl bg-white p-6 md:p-8" data-submit-form>
@csrf @if($employee->exists) @method('PUT') @endif
<div class="grid gap-5 md:grid-cols-2">
<div><label class="filter-label" for="business_id">ID Karyawan (otomatis, tetap)</label><input class="field" id="business_id" value="{{ $employee->business_id ?? 'Diterbitkan saat profil disimpan' }}" readonly></div>
<div><label class="filter-label" for="date_of_birth">Tanggal lahir</label><input class="field" type="date" id="date_of_birth" name="date_of_birth" value="{{ old('date_of_birth',$employee->date_of_birth?->format('Y-m-d')) }}" max="{{ today()->toDateString() }}" required></div>
<div class="md:col-span-2"><label class="filter-label" for="home_address">Alamat rumah</label><textarea class="field" id="home_address" name="home_address" required maxlength="3000" rows="3">{{ old('home_address',$employee->home_address) }}</textarea></div>

@foreach(['employee_number'=>'NIK','name'=>'Nama lengkap','email'=>'Email profil','phone'=>'Nomor telepon','position'=>'Jabatan'] as $field=>$label)
<div><label class="filter-label" for="{{ $field }}">{{ $label }}</label><input class="field" id="{{ $field }}" name="{{ $field }}" value="{{ old($field,$employee->$field) }}" type="{{ $field==='email' ? 'email' : 'text' }}" maxlength="{{ $field==='employee_number' ? 50 : ($field==='phone' ? 30 : 255) }}" required></div>
@endforeach
<div><label for="division_id" class="filter-label">Divisi</label><select id="division_id" name="division_id" class="field" required><option value="">Pilih divisi</option>@foreach($divisions as $division)<option value="{{ $division->id }}" @selected((string)old('division_id',$employee->division_id)===(string)$division->id)>{{ $division->name }}</option>@endforeach</select></div>
<div><label for="is_active" class="filter-label">Status karyawan</label><select id="is_active" name="is_active" class="field"><option value="1" @selected((string)old('is_active',$employee->exists ? (int)$employee->is_active : 1)==='1')>Aktif</option><option value="0" @selected((string)old('is_active',$employee->exists ? (int)$employee->is_active : 1)==='0')>Nonaktif</option></select></div>
<div><label for="user_id" class="filter-label">Akun login (opsional)</label><select id="user_id" name="user_id" class="field"><option value="">Tidak terhubung / lepaskan tautan</option>@foreach($accounts as $account)<option value="{{ $account->id }}" @selected((string)old('user_id',$employee->user_id)===(string)$account->id)>{{ $account->name }} / {{ $account->email }}{{ $account->is_active ? '' : ' (Nonaktif)' }}</option>@endforeach</select></div>
</div>
@if($employee->user_id && !$accounts->contains('id',$employee->user_id))<p class="mt-4 text-sm text-amber-800">Akun tertaut saat ini tidak aktif atau bukan HR/Employee. Aktifkan melalui Admin atau pilih akun lain / lepaskan tautan.</p>@endif
<p class="mt-4 text-xs leading-5 text-slate-500">Hanya akun HR/Employee aktif yang belum dipakai profil lain dapat dihubungkan. Tautan akun nonaktif yang sudah ada dapat dipertahankan. Perubahan email profil yang tertaut memperbarui email login dan mencabut sesi/token lama; undangan pending perlu dikirim ulang. Menonaktifkan profil tidak mengubah status akun; hubungi Admin untuk mencabut akses login.</p>
<div class="mt-7 flex gap-5 items-center"><button class="button" data-submit-button>Simpan profil</button><a href="{{ route('hr.employees.index') }}" class="reset-link">Batal</a></div>
</form>
</x-layout>
