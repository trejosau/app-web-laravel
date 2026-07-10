<?php

namespace App\Policies;

use App\Models\Role;
use App\Models\SecurityAuditLog;
use App\Models\User;

class SecurityAuditLogPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasRole(Role::ADMIN);
    }

    public function view(User $user, SecurityAuditLog $securityAuditLog): bool
    {
        return $user->hasRole(Role::ADMIN);
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, SecurityAuditLog $securityAuditLog): bool
    {
        return false;
    }

    public function delete(User $user, SecurityAuditLog $securityAuditLog): bool
    {
        return false;
    }
}
