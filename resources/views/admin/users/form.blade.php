<x-layout :title="$account->exists ? 'Edit Akun' : 'Tambah Akun'">
<a href="{{ route('admin.users.index') }}" class="reset-link">&larr; Kembali ke akun</a>
<h1 class="mt-4 text-3xl font-bold text-purple">{{ $account->exists ? 'Edit akun pengguna' : 'Tambah akun pengguna' }}</h1>
<p class="mt-2 text-sm text-slate-500">Profil karyawan dihubungkan oleh HR. Perubahan akses atau password akan mengakhiri sesi database akun tersebut.</p>
<form method="POST" action="{{ $account->exists ? route('admin.users.update',$account) : route('admin.users.store') }}" class="mt-7 max-w-3xl rounded-2xl border border-slate-100 bg-white p-6 md:p-8" data-submit-form>
@csrf
@if($account->exists) @method('PUT') @endif
<div class="grid gap-5 md:grid-cols-2">
<div><label for="name" class="filter-label">Nama lengkap</label><input class="field" id="name" name="name" value="{{ old('name',$account->name) }}" required maxlength="255" autocomplete="name"></div>
<div><label for="email" class="filter-label">Email</label><input class="field" id="email" name="email" type="email" value="{{ old('email',$account->email) }}" required maxlength="255" autocomplete="off"></div>
<div><label for="role" class="filter-label">Role</label><select class="field" id="role" name="role" required>@foreach(['employee'=>'Employee','hr'=>'HR'] as $value=>$label)<option value="{{ $value }}" @selected(old('role',$account->role?->value ?? 'employee')===$value)>{{ $label }}</option>@endforeach</select></div>
<div><label for="is_active" class="filter-label">Status akun</label><select class="field" id="is_active" name="is_active"><option value="1" @selected((string)old('is_active',$account->exists ? (int)$account->is_active : 1)==='1')>Aktif</option><option value="0" @selected((string)old('is_active',$account->exists ? (int)$account->is_active : 1)==='0')>Nonaktif</option></select></div>
<div><label for="password" class="filter-label">{{ $account->exists ? 'Password baru (opsional)' : 'Password' }}</label><input class="field" id="password" aria-describedby="password-help" name="password" type="password" autocomplete="new-password" minlength="12" maxlength="255" @required(!$account->exists)></div>
<div><label for="password_confirmation" class="filter-label">Konfirmasi password</label><input class="field" id="password_confirmation" name="password_confirmation" type="password" autocomplete="new-password" minlength="12" maxlength="255" @required(!$account->exists)></div>
</div>
<p id="password-help" class="mt-4 text-xs leading-5 text-slate-500">Minimal 12 karakter, huruf besar, huruf kecil, angka, dan simbol. {{ $account->exists ? 'Kosongkan kedua kolom untuk mempertahankan password saat ini.' : '' }}</p>
@if($account->employee)<p class="mt-4 rounded-xl bg-lavender p-4 text-sm text-purple">Terhubung ke {{ $account->employee->name }} ({{ $account->employee->employee_number }}). Role harus tetap Employee selama profil terhubung.</p>@endif
<div class="mt-7 flex items-center gap-5"><button class="button" data-submit-button>Simpan akun</button><a href="{{ route('admin.users.index') }}" class="reset-link">Batal</a></div>
</form>
</x-layout>
