<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;

class EnsuresPermission
{
    /**
     * @param list<string> $permissions
     */
    public static function any(User $user, array $permissions): void
    {
        if ($user->grantsAllPermissions()) {
            return;
        }

        foreach ($permissions as $permission) {
            if (UserPermissions::allows($user, $permission)) {
                return;
            }
        }

        throw new AuthorizationException('You do not have permission to perform this action.');
    }

    public static function one(User $user, string $permission): void
    {
        self::any($user, [$permission]);
    }
}
