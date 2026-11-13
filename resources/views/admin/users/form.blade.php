<x-layout :title="$account->exists ? 'Edit Akses Akun' : 'Send Invitation'">
<a href="{{ route('admin.users.index') }}" class="reset-link">&larr; Kembali ke akun</a>
<h1 class="mt-4 text-3xl font-bold text-purple">{{ $account->exists ? 'Edit akses akun' : 'Undang karyawan' }}</h1>
<p class="mt-2 text-sm text-slate-500">Pengguna membuat password sendiri melalui email. Admin tidak mengatur password.</p>
<form method="POST" action="{{ $account->exists ? route('admin.users.update',$account) : route('admin.users.store') }}" class="mt-7 max-w-3xl rounded-2xl bg-white p-8 space-y-5" data-submit-form>
@csrf @if($account->exists) @method('PUT')
<p class="font-medium">{{ $account->name }}</p><p class="text-sm text-slate-500">{{ $account->email }}</p>
<div><label class="filter-label" for="account_status">Status akun</label><select class="field" id="account_status" name="account_status">
@foreach(($account->activated_at ? ['active'=>'Active','disabled'=>'Disabled'] : ['pending_activation'=>'Pending Activation','disabled'=>'Disabled']) as $value=>$label)<option value="{{ $value }}" @selected(old('account_status',$account->account_status)===$value)>{{ $label }}</option>@endforeach
</select></div>
@else
@if($employees->isEmpty())<p role="status" class="rounded-xl bg-lavender p-4 text-sm text-purple">Belum ada karyawan yang dapat diundang. Lengkapi profil aktif dan ID Karyawan melalui HR terlebih dahulu.</p>@endif
<div><label class="filter-label" for="employee-search">Cari ID Karyawan / nama</label><input class="field" id="employee-search" type="search" data-employee-search autocomplete="off"></div>
<div><label class="filter-label" for="employee_id">ID Karyawan</label><select class="field" name="employee_id" id="employee_id" required data-invitation-employee><option value="">Pilih karyawan aktif</option>@foreach($employees as $employee)<option value="{{ $employee->id }}" data-email="{{ $employee->email }}" @selected((string)old('employee_id')===(string)$employee->id)>{{ $employee->business_id }} / {{ $employee->name }}</option>@endforeach</select></div>
<div><label class="filter-label" for="employee_email">Email</label><input class="field" id="employee_email" type="email" readonly data-invitation-email></div>
<p class="text-xs text-slate-500">Karyawan harus aktif, ID sudah diterbitkan, dan belum memiliki akun. Lengkapi profil melalui HR jika belum muncul.</p>
@endif
<div><label class="filter-label" for="role">Role</label><select class="field" id="role" name="role">@foreach(['employee'=>'Employee','hr'=>'HR'] as $value=>$label)<option value="{{ $value }}" @selected(old('role',$account->role?->value??'employee')===$value)>{{ $label }}</option>@endforeach</select></div>
<button class="button" data-submit-button>{{ $account->exists ? 'Simpan akses' : 'Send Invitation' }}</button>
</form></x-layout>
