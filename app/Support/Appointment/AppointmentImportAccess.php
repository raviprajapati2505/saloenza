<?php

namespace App\Support\Appointment;

use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;

final class AppointmentImportAccess
{
    public static function allows(User $user): bool
    {
        return $user->is_system_admin || $user->isFranchiseOwner();
    }

    public static function assert(User $user): void
    {
        if (! self::allows($user)) {
            throw new AuthorizationException('Only a super admin or salon owner can import appointments.');
        }
    }
}
