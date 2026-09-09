<?php

namespace App\Support;

use App\Models\Permission;
use App\Models\User;
use Illuminate\Support\Collection;

class UserPermissions
{
    /**
     * @return Collection<int, string>
     */
    public static function codesFor(User $user): Collection
    {
        if ($user->grantsAllPermissions()) {
            return Permission::query()
                ->orderBy('module')
                ->orderBy('code')
                ->pluck('code');
        }

        if (! $user->relationLoaded('role')) {
            $user->load('role.permissions');
        } elseif ($user->role && ! $user->role->relationLoaded('permissions')) {
            $user->role->load('permissions');
        }

        return $user->role?->permissions
            ->pluck('code')
            ->values() ?? collect();
    }

    public static function allows(User $user, string $code): bool
    {
        if ($user->grantsAllPermissions()) {
            return true;
        }

        return self::codesFor($user)->contains($code);
    }

    /**
     * @param list<string> $codes
     */
    public static function allowsAny(User $user, array $codes): bool
    {
        if ($user->grantsAllPermissions()) {
            return true;
        }

        $granted = self::codesFor($user);

        foreach ($codes as $code) {
            if ($granted->contains($code)) {
                return true;
            }
        }

        return false;
    }
}
