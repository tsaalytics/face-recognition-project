<x-layout title="Akun Pengguna">
<div class="compact-list">
<x-list-toolbar title="Akun Pengguna" breadcrumb="Akun Pengguna" :dashboard="route('admin.home')" :action="route('admin.users.index')" :paginator="$users" :filters="['q', 'role', 'account_status', 'active']" unit="akun" description="Kelola undangan dan akses HR/Employee. Akun Admin dilindungi.">
<x-slot:actions>@can('create', \App\Models\User::class)<a href="{{ route('admin.users.create') }}" class="list-control list-primary"><x-icon name="plus"/>Tambah akun</a>@endcan</x-slot:actions>
<div><label for="q">Nama / email</label><input class="field" id="q" name="q" value="{{ request('q') }}" placeholder="Cari akun..."></div>
<div><label for="role">Role</label><select id="role" name="role" class="field"><option value="">Semua role</option>@foreach(['admin'=>'Admin', 'hr'=>'HR', 'employee'=>'Employee'] as $value=>$label)<option value="{{ $value }}" @selected(request('role') === $value)>{{ $label }}</option>@endforeach</select></div>
<div><label for="account_status">Status akun</label><select id="account_status" name="account_status" class="field"><option value="">Semua status</option>@foreach(['pending_activation'=>'Pending Activation', 'active'=>'Active', 'disabled'=>'Disabled'] as $value=>$label)<option value="{{ $value }}" @selected(request('account_status') === $value)>{{ $label }}</option>@endforeach</select></div>
<div><label for="active">Status akses</label><select id="active" name="active" class="field"><option value="">Semua akses</option><option value="1" @selected(request('active') === '1')>Aktif</option><option value="0" @selected(request('active') === '0')>Nonaktif</option></select></div>
</x-list-toolbar>
<div class="table-panel" role="region" aria-label="Tabel data, geser untuk melihat semua kolom" tabindex="0"><table class="data-table"><thead><tr><th scope="col">Pengguna</th><th scope="col">Role</th><th scope="col">Status</th><th scope="col">Aksi</th></tr></thead><tbody>
@forelse($users as $user)
<tr><td><p class="font-medium">{{ $user->name }}</p><p class="mt-1 text-xs text-slate-500">{{ $user->email }}</p></td><td>{{ strtoupper($user->role->value) }}</td><td><span class="text-xs font-semibold text-purple">{{ ['active'=>'Active','pending_activation'=>'Pending Activation','disabled'=>'Disabled'][$user->account_status] ?? $user->account_status }}</span>@if($user->invitation_sent_at)<p class="mt-1 text-xs text-slate-500">Undangan: {{ $user->invitation_sent_at->format('d M Y H:i') }}</p>@endif</td><td>@can('update',$user)<a href="{{ route('admin.users.edit',$user) }}" class="reset-link">Edit akun</a>@if($user->account_status==='pending_activation')<form class="mt-3" method="POST" action="{{ route('admin.users.invitation',$user) }}" data-submit-form>@csrf<button class="reset-link" data-submit-button>Kirim ulang undangan</button></form>@endif@else<span class="text-xs text-slate-500">Dilindungi</span>@endcan</td></tr>
@empty<tr><td colspan="4" class="empty-state">Tidak ada akun yang cocok dengan filter.</td></tr>@endforelse
</tbody></table></div>
<x-list-pagination :paginator="$users"/>
</div>
</x-layout>
