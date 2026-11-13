<?php

namespace App\Services;

use App\Enums\Role;
use App\Models\Employee;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class EmployeeProfileService
{
    public function save(User $actor, array $data, ?Employee $employee = null): Employee
    {
        return DB::transaction(function () use ($actor, $data, $employee) {
            if ($employee && ! array_key_exists('user_id', $data)) {
                $data['user_id'] = $employee->user_id;
            }
            $account = null;
            // Lock linked account as well so Admin role changes cannot race with linking.
            if (! empty($data['user_id'])) {
                $account = User::whereKey($data['user_id'])->lockForUpdate()->first();
                if (! $account || (! in_array($account->role, [Role::Employee, Role::Hr], true)) || (! $account->is_active && $employee?->user_id !== $account->id)) {
                    throw ValidationException::withMessages(['user_id' => 'Pilih akun HR/Employee aktif.']);
                }
                if (Employee::where('user_id', $account->id)->when($employee, fn ($q) => $q->where('id', '!=', $employee->id))->exists()) {
                    throw ValidationException::withMessages(['user_id' => 'Akun sudah terhubung ke karyawan lain.']);
                }
            }
            if ($employee) {
                $employee = Employee::whereKey($employee->id)->lockForUpdate()->firstOrFail();
            }
            Gate::forUser($actor)->authorize($employee ? 'update' : 'create', $employee ?? Employee::class);
            if ($account && $employee?->user_id !== $account->id && $account->email !== $data['email']) {
                throw ValidationException::withMessages(['email' => 'Email profil harus sama dengan akun yang akan ditautkan.']);
            }
            $employee ??= new Employee;

            $employee->fill(collect($data)->except(['business_id'])->all());
            if ($account && $employee->isDirty('email')) {
                $account->email = $employee->email;
                $account->remember_token = Str::random(60);
                Password::deleteToken($account);
                // Invalidate the previous address token too.
                DB::table(config('auth.passwords.users.table'))->where('email', $account->getOriginal('email'))->delete();
                if (config('session.driver') === 'database') {
                    DB::table(config('session.table', 'sessions'))->where('user_id', $account->id)->delete();
                }
                $account->save();
            }
            if (! $employee->business_id && $employee->date_of_birth) {
                for ($attempt = 0; $attempt < 100; $attempt++) {
                    $employee->business_id = app(EmployeeIdGenerator::class)->generate($employee->date_of_birth->toDateString());
                    try {
                        $employee->save();
                        break;
                    } catch (UniqueConstraintViolationException $e) {
                        if (! str_contains($e->getMessage(), 'business_id') || $attempt === 99) {
                            throw $e;
                        }
                    }
                }
            } else {
                $employee->save();
            }

            return $employee;
        }, 3);
    }
}
