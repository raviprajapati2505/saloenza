<?php

namespace App\Policies;

use App\Models\Role;
use App\Models\User;
use App\Support\Role\RoleAccess;
use App\Support\UserPermissions;
use Illuminate\Auth\Access\AuthorizationException;

class RolePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->is_system_admin || UserPermissions::allows($user, 'roles.view');
    }

    public function view(User $user, Role $role): bool
    {
        try {
            RoleAccess::ensureAccessible($user, $role);
        } catch (AuthorizationException) {
            return false;
        }

        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $user->is_system_admin || UserPermissions::allows($user, 'roles.create');
    }

    public function update(User $user, Role $role): bool
    {
        return ($user->is_system_admin || UserPermissions::allows($user, 'roles.update'))
            && $this->view($user, $role);
    }

    public function delete(User $user, Role $role): bool
    {
        return ($user->is_system_admin || UserPermissions::allows($user, 'roles.delete'))
            && $this->view($user, $role);
    }

    public function assignPermissions(User $user, Role $role): bool
    {
        return ($user->is_system_admin || UserPermissions::allows($user, 'assign_permissions.update'))
            && $this->view($user, $role);
    }
}
