<?php

namespace App\Support\Customer;

use App\Models\Customer;
use App\Models\User;
use App\Support\UserPermissions;

final class CustomerContactAccess
{
    public const PERMISSION = 'customers.contacts.view';

    public static function canViewAll(?User $user): bool
    {
        if ($user === null) {
            return false;
        }

        return UserPermissions::allows($user, self::PERMISSION);
    }

    public static function canView(?User $user, ?Customer $customer): bool
    {
        if ($user === null || $customer === null) {
            return false;
        }

        if (self::canViewAll($user)) {
            return true;
        }

        return $customer->created_by !== null
            && (int) $customer->created_by === (int) $user->id;
    }

    public static function canSearchByContact(?User $user): bool
    {
        return self::canViewAll($user);
    }

    public static function visiblePhone(?User $user, ?Customer $customer): ?string
    {
        if (! self::canView($user, $customer)) {
            return null;
        }

        $phone = $customer?->phone;

        return filled($phone) ? (string) $phone : null;
    }

    public static function visibleEmail(?User $user, ?Customer $customer): ?string
    {
        if (! self::canView($user, $customer)) {
            return null;
        }

        $email = $customer?->email;

        return filled($email) ? (string) $email : null;
    }
}
