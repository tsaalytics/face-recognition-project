<?php

namespace App\Services;

use App\Mail\AccountInvitation;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class InvitationService
{
    public function send(User $actor, User $user): bool
    {
        $token = DB::transaction(function () use ($actor, $user) {
            $user = User::whereKey($user->id)->lockForUpdate()->firstOrFail();
            Gate::forUser($actor)->authorize('update', $user);
            if ($user->account_status !== 'pending_activation' || $user->activated_at) {
                throw ValidationException::withMessages(['invitation' => 'Hanya akun Pending Activation yang dapat diundang.']);
            }
            if (! $user->employee?->is_active || $user->employee->email !== $user->email) {
                throw ValidationException::withMessages(['invitation' => 'Profil harus aktif dan email harus sesuai akun.']);
            }
            if (($user->invitation_sent_at && $user->invitation_sent_at->gt(now()->subMinute())) || Password::broker()->getRepository()->recentlyCreatedToken($user)) {
                throw ValidationException::withMessages(['invitation' => 'Tunggu 60 detik sebelum mengirim ulang undangan.']);
            }

            return Password::createToken($user);
        });
        $url = route('invitation.show', ['token' => $token, 'email' => $user->email]);
        try {
            Mail::to($user->email)->send(new AccountInvitation($user->employee->name, $url, (int) config('auth.passwords.users.expire')));
            $user->forceFill(['invitation_sent_at' => now()])->save();

            return true;
        } catch (\Throwable $e) {
            // Account stays pending; Admin can retry after broker cooldown. Never expose token via UI.
            report($e);

            return false;
        }
    }

    public function activate(string $email, string $token, string $password): void
    {
        DB::transaction(function () use ($email, $token, $password) {
            $user = User::where('email', $email)->lockForUpdate()->first();
            if (! $user || $user->account_status !== 'pending_activation' || $user->activated_at || ! Password::tokenExists($user, $token) || ! $user->employee?->is_active || $user->employee->email !== $email) {
                throw ValidationException::withMessages(['token' => 'Undangan tidak valid, sudah digunakan, atau kedaluwarsa. Hubungi Admin untuk mengirim ulang.']);
            }
            $user->forceFill(['password' => $password, 'account_status' => 'active', 'is_active' => true, 'activated_at' => now(), 'email_verified_at' => now(), 'remember_token' => Str::random(60)])->save();
            Password::deleteToken($user);
        });
    }
}
