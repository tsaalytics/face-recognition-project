<?php

namespace App\Policies;

use App\Enums\Role;
use App\Models\Employee;
use App\Models\User;

class EmployeePolicy
{
    public function manageFace(User $user, Employee $record): bool
    {
        return $user->is_active && $user->account_status === 'active' && $user->role === Role::Hr;
    }

    public function viewAny(User $user): bool
    {
        return $user->is_active;
    }

    public function view(User $user, Employee $record): bool
    {
        return $user->is_active && (in_array($user->role, [Role::Admin, Role::Hr], true) || $record->user_id === $user->id);
    }

    public function create(User $user): bool
    {
        return $user->is_active && $user->role === Role::Hr;
    }

    public function update(User $user, Employee $record): bool
    {
        return $user->is_active && $user->role === Role::Hr;
    }

    public function delete(User $user, Employee $record): bool
    {
        return false;
    }
}
