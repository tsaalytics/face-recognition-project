<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\MonitorRequest;
use App\Http\Requests\Admin\UserRequest;
use App\Models\Employee;
use App\Models\User;
use App\Services\InvitationService;
use App\Services\UserAccountService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class UserController extends Controller
{
    public function index(MonitorRequest $request)
    {
        Gate::authorize('viewAny', User::class);
        $f = $request->validated();
        $users = User::query()->when($f['q'] ?? null, fn ($q, $s) => $q->where(fn ($q) => $q->where('name', 'like', "%$s%")->orWhere('email', 'like', "%$s%")))
            ->when($f['account_status'] ?? null, fn ($q, $status) => $q->where('account_status', $status))
            ->when($f['role'] ?? null, fn ($q, $r) => $q->where('role', $r))
            ->when(isset($f['active']), fn ($q) => $q->where('is_active', $f['active']))
            ->orderBy('name')->orderBy('id')->paginate(15)->withQueryString();

        return view('admin.users.index', compact('users'));
    }

    public function create()
    {
        Gate::authorize('create', User::class);

        return view('admin.users.form', ['account' => new User, 'employees' => Employee::where('is_active', true)->whereNull('user_id')->whereNotNull('business_id')->whereNotNull('date_of_birth')->whereDoesntHave('user')->orderBy('business_id')->get()]);
    }

    public function store(UserRequest $request, UserAccountService $service, InvitationService $invitations)
    {
        $user = $service->save($request->user(), $request->validated());
        $sent = $invitations->send($request->user(), $user);

        return redirect()->route('admin.users.index')->with($sent ? 'success' : 'warning', $sent ? 'Akun Pending Activation dibuat. '.$this->deliveryMessage() : 'Akun dibuat, tetapi pengiriman email gagal. Periksa mail configuration lalu kirim ulang setelah 60 detik.');
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

    public function resend(Request $request, User $user, InvitationService $service)
    {
        Gate::authorize('update', $user);
        $sent = $service->send($request->user(), $user);

        return back()->with($sent ? 'success' : 'warning', $sent ? $this->deliveryMessage() : 'Pengiriman email gagal. Periksa mail configuration dan coba setelah 60 detik.');
    }

    private function deliveryMessage(): string
    {
        $transport = config('mail.mailers.'.config('mail.default').'.transport');

        return match ($transport) {
            'log' => 'Undangan disimpan ke Mail Log; tidak dikirim ke inbox.',
            'array' => 'Undangan disimpan pada mailer pengujian; tidak dikirim ke inbox.',
            default => 'Undangan diteruskan ke layanan email. Periksa inbox dan spam.',
        };
    }
}
