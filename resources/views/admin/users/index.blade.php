<x-layout title="Akun Pengguna">
<div class="flex flex-wrap items-start justify-between gap-4"><div><h1 class="text-3xl font-bold text-purple">Akun pengguna</h1><p class="mt-2 text-sm text-slate-500">Kelola akses HR dan Employee. Akun Admin dilindungi.</p></div><a href="{{ route('admin.users.create') }}" class="button">+ Tambah akun</a></div>
<form method="GET" class="filter-panel" aria-label="Filter akun">
<div class="grow"><label for="q" class="filter-label">Nama / email</label><input class="field" id="q" name="q" value="{{ request('q') }}" placeholder="Cari akun..."></div>
<div><label for="role" class="filter-label">Role</label><select id="role" name="role" class="field"><option value="">Semua role</option>@foreach(['admin'=>'Admin','hr'=>'HR','employee'=>'Employee'] as $value=>$label)<option value="{{ $value }}" @selected(request('role')===$value)>{{ $label }}</option>@endforeach</select></div>
<div><label for="active" class="filter-label">Status</label><select id="active" name="active" class="field"><option value="">Semua status</option><option value="1" @selected(request('active')==='1')>Aktif</option><option value="0" @selected(request('active')==='0')>Nonaktif</option></select></div>
<button class="button">Terapkan</button><a href="{{ route('admin.users.index') }}" class="reset-link">Reset</a>
</form>
<div class="table-panel"><table class="data-table"><thead><tr><th>Pengguna</th><th>Role</th><th>Status</th><th>Aksi</th></tr></thead><tbody>
@forelse($users as $user)
<tr><td><p class="font-medium">{{ $user->name }}</p><p class="mt-1 text-xs text-slate-500">{{ $user->email }}</p></td><td>{{ strtoupper($user->role->value) }}</td><td><x-status :value="$user->is_active ? 'active' : 'inactive'"/></td><td>@can('update',$user)<a href="{{ route('admin.users.edit',$user) }}" class="reset-link">Edit akun</a>@else<span class="text-xs text-slate-500">Dilindungi</span>@endcan</td></tr>
@empty<tr><td colspan="4" class="empty-state">Tidak ada akun yang cocok dengan filter.</td></tr>@endforelse
</tbody></table></div>
<div class="mt-5">{{ $users->links() }}</div>
</x-layout>
