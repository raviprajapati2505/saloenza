<?php

namespace App\Support\Concerns;

use App\Models\User;
use App\Support\UserPermissions;

trait AuthorizesPermission
{
    protected function userCan(string $permission): bool
    {
        $user = $this->user();

        if (! $user instanceof User) {
            return false;
        }

        return UserPermissions::allows($user, $permission);
    }

    /**
     * @param  list<string>  $permissions
     */
    protected function userCanAny(array $permissions): bool
    {
        $user = $this->user();

        return $user instanceof User && UserPermissions::allowsAny($user, $permissions);
    }

    protected function userIsSystemAdmin(): bool
    {
        $user = $this->user();

        return $user instanceof User && $user->is_system_admin;
    }

    protected function userCanAsPlatform(string $permission): bool
    {
        $user = $this->user();

        return $user instanceof User
            && $user->isPlatformActor()
            && UserPermissions::allows($user, $permission);
    }
}
