<?php

namespace App\Policies;

use App\Enums\Role;
use App\Models\AttendanceCorrectionRequest;
use App\Models\User;

class AttendanceCorrectionRequestPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->is_active;
    }

    public function view(User $user, AttendanceCorrectionRequest $record): bool
    {
        return $user->is_active && (in_array($user->role, [Role::Admin, Role::Hr], true) || $record->employee?->user_id === $user->id);
    }

    public function create(User $user): bool
    {
        return $user->is_active && $user->role === Role::Employee && $user->employee?->is_active === true;
    }

    public function update(User $user, AttendanceCorrectionRequest $record): bool
    {
        return false;
    }

    public function delete(User $user, AttendanceCorrectionRequest $record): bool
    {
        return false;
    }

    public function approve(User $user, AttendanceCorrectionRequest $record): bool
    {
        return $user->is_active && $user->role === Role::Hr && $record->status === 'pending' && $record->employee?->user_id !== $user->id;
    }

    public function reject(User $user, AttendanceCorrectionRequest $record): bool
    {
        return $this->approve($user, $record);
    }
}
