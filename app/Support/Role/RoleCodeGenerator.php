<?php

namespace App\Support\Role;

use App\Models\Role;
use Illuminate\Support\Str;

class RoleCodeGenerator
{
    public static function forCustomRole(string $displayName, ?int $saloonId): string
    {
        $slug = Str::slug(Str::lower(trim($displayName)), '_');
        if ($slug === '') {
            $slug = 'role';
        }

        $prefix = $saloonId !== null
            ? "salon.custom.{$saloonId}"
            : 'global.custom';

        $code = "{$prefix}.{$slug}";
        $counter = 1;

        while (Role::query()->where('code', $code)->exists()) {
            $code = "{$prefix}.{$slug}_{$counter}";
            $counter++;
        }

        return $code;
    }

    public static function forExistingRole(Role $role): string
    {
        if ($role->saloon_id === null && $role->name === RoleCodes::LEGACY_SALOON_OWNER) {
            return RoleCodes::SALON_FRANCHISE_OWNER;
        }

        return self::forCustomRole($role->name, $role->saloon_id);
    }
}
