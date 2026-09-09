<?php

namespace App\Support\Saloon;

use App\Models\Saloon;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;

class SaloonAccess
{
    public static function ensureReadable(User $actor, Saloon $saloon): void
    {
        if ($actor->is_system_admin) {
            return;
        }

        if ($actor->saloon_id === null) {
            throw new AuthorizationException('Your account is not assigned to a saloon.');
        }

        if ((int) $actor->saloon_id !== (int) $saloon->id) {
            throw new AuthorizationException('You can only view service products for your current saloon.');
        }
    }

    public static function ensureWritable(User $actor, Saloon $saloon): void
    {
        self::ensureReadable($actor, $saloon);
    }
}
