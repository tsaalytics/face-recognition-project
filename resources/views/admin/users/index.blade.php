<x-layout title="Akun Pengguna">
<div class="flex flex-wrap items-start justify-between gap-4"><div><h1 class="text-3xl font-bold text-purple">Akun pengguna</h1><p class="mt-2 text-sm text-slate-500">Kelola undangan dan akses HR/Employee. Akun Admin dilindungi.</p></div><a href="{{ route('admin.users.create') }}" class="button">+ Tambah akun</a></div>
<form method="GET" class="filter-panel" aria-label="Filter akun">
<div class="grow"><label for="q" class="filter-label">Nama / email</label><input class="field" id="q" name="q" value="{{ request('q') }}" placeholder="Cari akun..."></div>
<div><label for="role" class="filter-label">Role</label><select id="role" name="role" class="field"><option value="">Semua role</option>@foreach(['admin'=>'Admin','hr'=>'HR','employee'=>'Employee'] as $value=>$label)<option value="{{ $value }}" @selected(request('role')===$value)>{{ $label }}</option>@endforeach</select></div>
<div><label for="account_status" class="filter-label">Status akun</label><select id="account_status" name="account_status" class="field"><option value="">Semua status</option>@foreach(['pending_activation'=>'Pending Activation','active'=>'Active','disabled'=>'Disabled'] as $value=>$label)<option value="{{ $value }}" @selected(request('account_status')===$value)>{{ $label }}</option>@endforeach</select></div>
<button class="button">Terapkan</button><a href="{{ route('admin.users.index') }}" class="reset-link">Reset</a>
</form>
<div class="table-panel" role="region" aria-label="Tabel data, geser untuk melihat semua kolom" tabindex="0"><table class="data-table"><thead><tr><th scope="col">Pengguna</th><th scope="col">Role</th><th scope="col">Status</th><th scope="col">Aksi</th></tr></thead><tbody>
@forelse($users as $user)
<tr><td><p class="font-medium">{{ $user->name }}</p><p class="mt-1 text-xs text-slate-500">{{ $user->email }}</p></td><td>{{ strtoupper($user->role->value) }}</td><td><span class="text-xs font-semibold text-purple">{{ ['active'=>'Active','pending_activation'=>'Pending Activation','disabled'=>'Disabled'][$user->account_status] ?? $user->account_status }}</span>@if($user->invitation_sent_at)<p class="mt-1 text-xs text-slate-500">Undangan: {{ $user->invitation_sent_at->format('d M Y H:i') }}</p>@endif</td><td>@can('update',$user)<a href="{{ route('admin.users.edit',$user) }}" class="reset-link">Edit akun</a>@if($user->account_status==='pending_activation')<form class="mt-3" method="POST" action="{{ route('admin.users.invitation',$user) }}" data-submit-form>@csrf<button class="reset-link" data-submit-button>Kirim ulang undangan</button></form>@endif@else<span class="text-xs text-slate-500">Dilindungi</span>@endcan</td></tr>
@empty<tr><td colspan="4" class="empty-state">Tidak ada akun yang cocok dengan filter.</td></tr>@endforelse
</tbody></table></div>
<div class="mt-5">{{ $users->links() }}</div>
</x-layout>
