<?php

namespace App\Policies;

use App\Models\Role;
use App\Models\User;

class UserPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasRole(Role::ADMIN);
    }

    public function view(User $user, User $target): bool
    {
        return $user->hasRole(Role::ADMIN);
    }

    public function block(User $user, User $target): bool
    {
        return $user->hasRole(Role::ADMIN) && ! $user->is($target) && $this->wouldKeepAdmin($target);
    }

    public function activate(User $user, User $target): bool
    {
        return $user->hasRole(Role::ADMIN);
    }

    private function wouldKeepAdmin(User $target): bool
    {
        if ($target->role?->name !== Role::ADMIN) {
            return true;
        }

        return User::query()
            ->whereHas('role', fn ($query) => $query->where('name', Role::ADMIN))
            ->where('status', 'active')
            ->whereKeyNot($target->id)
            ->exists();
    }
}
