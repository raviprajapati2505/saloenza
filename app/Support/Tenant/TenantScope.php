<?php

namespace App\Support\Tenant;

use App\Models\Customer;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;

class TenantScope
{
    public static function resolveSaloonId(User $user, ?int $requestedSaloonId = null): int
    {
        if ($user->is_system_admin) {
            if ($requestedSaloonId === null) {
                throw new AuthorizationException('Saloon is required for this action.');
            }

            return $requestedSaloonId;
        }

        if ($user->saloon_id === null) {
            throw new AuthorizationException('Your account is not assigned to a saloon.');
        }

        if ($requestedSaloonId !== null && (int) $requestedSaloonId !== (int) $user->saloon_id) {
            throw new AuthorizationException('You cannot access resources outside your saloon.');
        }

        return (int) $user->saloon_id;
    }

    /**
     * Returns null for system admins when no saloon filter is provided (all tenants).
     */
    public static function resolveSaloonFilter(User $user, ?int $requestedSaloonId = null): ?int
    {
        if ($user->is_system_admin) {
            return $requestedSaloonId;
        }

        if ($user->saloon_id === null) {
            throw new AuthorizationException('Your account is not assigned to a saloon.');
        }

        if ($requestedSaloonId !== null && (int) $requestedSaloonId !== (int) $user->saloon_id) {
            throw new AuthorizationException('You cannot access resources outside your saloon.');
        }

        return (int) $user->saloon_id;
    }

    public static function ensureSaloonAccess(User $user, int $resourceSaloonId): void
    {
        if ($user->is_system_admin) {
            return;
        }

        if ((int) $user->saloon_id !== $resourceSaloonId) {
            throw new AuthorizationException('You cannot access resources outside your saloon.');
        }
    }

    public static function ensureCustomerAccess(User $user, Customer $customer): void
    {
        if ($user->is_system_admin) {
            return;
        }

        if ($user->saloon_id === null) {
            throw new AuthorizationException('Your account is not assigned to a saloon.');
        }

        if (! $customer->belongsToSaloon((int) $user->saloon_id)) {
            throw new AuthorizationException('You cannot access resources outside your saloon.');
        }
    }
}
