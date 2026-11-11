<?php

namespace App\Policies;

use App\Enums\Role;
use App\Models\User;

class UserPolicy
{
    public function viewAny(User $actor): bool
    {
        return $actor->is_active && $actor->role === Role::Admin;
    }

    public function create(User $actor): bool
    {
        return $this->viewAny($actor);
    }

    public function update(User $actor, User $user): bool
    {
        return $this->viewAny($actor) && $user->role !== Role::Admin;
    }
}
