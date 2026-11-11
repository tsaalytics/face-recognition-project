<?php

namespace App\Services;

use App\Enums\Role;
use App\Models\Employee;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class EmployeeProfileService
{
    public function save(User $actor, array $data, ?Employee $employee = null): Employee
    {
        return DB::transaction(function () use ($actor, $data, $employee) {
            // Lock linked account as well so Admin role changes cannot race with linking.
            if (! empty($data['user_id'])) {
                $account = User::whereKey($data['user_id'])->lockForUpdate()->first();
                if (! $account || $account->role !== Role::Employee || (! $account->is_active && $employee?->user_id !== $account->id)) {
                    throw ValidationException::withMessages(['user_id' => 'Pilih akun Employee aktif.']);
                }
                if (Employee::where('user_id', $account->id)->when($employee, fn ($q) => $q->where('id', '!=', $employee->id))->exists()) {
                    throw ValidationException::withMessages(['user_id' => 'Akun sudah terhubung ke karyawan lain.']);
                }
            }
            if ($employee) {
                $employee = Employee::whereKey($employee->id)->lockForUpdate()->firstOrFail();
            }
            Gate::forUser($actor)->authorize($employee ? 'update' : 'create', $employee ?? Employee::class);
            $employee ??= new Employee;
            $employee->fill($data)->save();

            return $employee;
        }, 3);
    }
}
