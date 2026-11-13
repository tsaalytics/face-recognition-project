<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\InvitationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\Rules\Password as PasswordRule;

class InvitationController extends Controller
{
    public function show(Request $request, string $token)
    {
        $email = (string) $request->query('email', '');
        $user = User::where('email', $email)->first();
        $valid = $user && $user->account_status === 'pending_activation' && ! $user->activated_at && Password::tokenExists($user, $token);

        return response()->view('auth.activate', compact('email', 'token', 'valid'), $valid ? 200 : 410)->header('Cache-Control', 'no-store')->header('Referrer-Policy', 'no-referrer');
    }

    public function store(Request $request, InvitationService $service)
    {
        $data = $request->validate(['email' => ['required', 'email'], 'token' => ['required', 'string', 'max:255'], 'password' => ['required', 'confirmed', 'max:255', PasswordRule::min(12)->mixedCase()->numbers()->symbols()]]);
        $service->activate($data['email'], $data['token'], $data['password']);

        return redirect()->route('login')->with('success', 'Akun aktif. Silakan masuk dengan password Anda.');
    }
}
