<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\MonitorRequest;
use App\Http\Requests\Admin\UserRequest;
use App\Models\User;
use App\Services\UserAccountService;
use Illuminate\Support\Facades\Gate;

class UserController extends Controller
{
    public function index(MonitorRequest $request)
    {
        Gate::authorize('viewAny', User::class);
        $f = $request->validated();
        $users = User::query()->when($f['q'] ?? null, fn ($q, $s) => $q->where(fn ($q) => $q->where('name', 'like', "%$s%")->orWhere('email', 'like', "%$s%")))
            ->when($f['role'] ?? null, fn ($q, $r) => $q->where('role', $r))
            ->when(isset($f['active']), fn ($q) => $q->where('is_active', $f['active']))
            ->orderBy('name')->orderBy('id')->paginate(15)->withQueryString();

        return view('admin.users.index', compact('users'));
    }

    public function create()
    {
        Gate::authorize('create', User::class);

        return view('admin.users.form', ['account' => new User]);
    }

    public function store(UserRequest $request, UserAccountService $service)
    {
        $service->save($request->user(), $request->validated());

        return redirect()->route('admin.users.index')->with('success', 'Akun berhasil dibuat.');
    }

    public function edit(User $user)
    {
        Gate::authorize('update', $user);

        return view('admin.users.form', ['account' => $user]);
    }

    public function update(UserRequest $request, User $user, UserAccountService $service)
    {
        $service->save($request->user(), $request->validated(), $user);

        return redirect()->route('admin.users.index')->with('success', 'Akun berhasil diperbarui.');
    }
}
