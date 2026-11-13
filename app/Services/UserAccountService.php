<?php

namespace App\Services;

use App\Models\Employee;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class UserAccountService
{
    public function save(User $actor, array $data, ?User $user = null): User
    {
        return DB::transaction(function () use ($actor, $data, $user) {
            Gate::forUser($actor)->authorize($user ? 'update' : 'create', $user ?? User::class);
            if (! $user) {
                $employee = Employee::whereKey($data['employee_id'])->lockForUpdate()->firstOrFail();
                if (! $employee->is_active || $employee->user_id || ! $employee->business_id || ! $employee->date_of_birth) {
                    throw ValidationException::withMessages(['employee_id' => 'Pilih karyawan aktif, data lengkap dan ID sudah terbit, yang belum memiliki akun.']);
                }
                if (User::where('email', $employee->email)->exists()) {
                    throw ValidationException::withMessages(['employee_id' => 'Email profil sudah digunakan akun lain. Hubungi HR/Admin untuk meninjau relasi existing.']);
                }
                $user = new User(['name' => $employee->name, 'email' => $employee->email, 'password' => Str::random(64)]);
                $user->forceFill(['role' => $data['role'], 'account_status' => 'pending_activation', 'is_active' => false])->save();
                $employee->user_id = $user->id;
                $employee->save();

                return $user;
            }
            $user = User::whereKey($user->id)->lockForUpdate()->firstOrFail();
            Gate::forUser($actor)->authorize('update', $user);
            $status = $data['account_status'];
            if (($status === 'active' && ! $user->activated_at) || ($status === 'pending_activation' && $user->activated_at)) {
                throw ValidationException::withMessages(['account_status' => 'Akun belum aktivasi wajib membuat password melalui undangan; akun teraktivasi tidak dapat dijadikan pending.']);
            }
            $changed = $user->role->value !== $data['role'] || $user->account_status !== $status;
            $user->role = $data['role'];
            $user->account_status = $status;
            $user->is_active = $status === 'active';
            if ($changed) {
                $user->remember_token = Str::random(60);
                Password::deleteToken($user);
                if (config('session.driver') === 'database') {
                    DB::table(config('session.table', 'sessions'))->where('user_id', $user->id)->delete();
                }
            }
            $user->save();

            return $user;
        }, 3);
    }
}
