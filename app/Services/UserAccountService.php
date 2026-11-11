<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class UserAccountService
{
    public function save(User $actor, array $data, ?User $user = null): User
    {
        return DB::transaction(function () use ($actor, $data, $user) {
            if ($user) {
                $user = User::whereKey($user->id)->lockForUpdate()->firstOrFail();
            }
            Gate::forUser($actor)->authorize($user ? 'update' : 'create', $user ?? User::class);
            if ($user?->employee()->exists() && $data['role'] !== 'employee') {
                throw ValidationException::withMessages(['role' => 'Akun terhubung ke profil karyawan. Lepaskan tautan melalui HR sebelum mengubah role.']);
            }
            $user ??= new User;
            $sensitive = $user->exists && ($user->email !== $data['email'] || $user->role->value !== $data['role'] || $user->is_active !== (bool) $data['is_active'] || ! empty($data['password']));
            $user->fill(collect($data)->only(['name', 'email'])->all());
            $user->role = $data['role'];
            $user->is_active = (bool) $data['is_active'];
            if (! empty($data['password'])) {
                $user->password = $data['password'];
            }
            if ($sensitive) {
                $user->remember_token = Str::random(60);
                if (config('session.driver') === 'database') {
                    DB::table(config('session.table', 'sessions'))->where('user_id', $user->id)->delete();
                }
            }
            $user->save();

            return $user;
        });
    }
}
