<?php

namespace App\Policies;

use App\Enums\Role;
use App\Models\AttendanceRecord;
use App\Models\User;

class AttendanceRecordPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->is_active;
    }

    public function view(User $user, AttendanceRecord $record): bool
    {
        return $user->is_active && (in_array($user->role, [Role::Admin, Role::Hr], true) || $record->employee?->user_id === $user->id);
    }

    public function create(User $user): bool
    {
        return $user->is_active && false;
    }

    public function update(User $user, AttendanceRecord $record): bool
    {
        return false;
    }

    public function delete(User $user, AttendanceRecord $record): bool
    {
        return false;
    }
}
