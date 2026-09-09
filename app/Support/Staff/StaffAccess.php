<?php

namespace App\Support\Staff;

use App\Models\User;
use App\Support\Branch\BranchScope;
use App\Support\EnsuresPermission;
use App\Support\Tenant\TenantScope;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Builder;

class StaffAccess
{
    public static function ensureReadable(User $actor): void
    {
        EnsuresPermission::one($actor, 'staff.view');
        BranchScope::ensureAssigned($actor);
    }

    public static function applyBranchScope(Builder $query, User $actor): void
    {
        $actor->loadMissing('role');

        if ($actor->isBranchScopedActor()) {
            BranchScope::ensureAssigned($actor);
            $query->where('branch_id', $actor->branch_id);
        }
    }

    public static function resolveBranchId(User $actor, mixed $requestedBranchId): ?int
    {
        return BranchScope::resolveBranchId($actor, $requestedBranchId);
    }

    public static function ensureWritable(User $actor, User $staff, string $permission): void
    {
        if ($staff->is_system_admin) {
            throw new AuthorizationException('System admin accounts cannot be managed through staff APIs.');
        }

        if ($staff->saloon_id === null) {
            throw new AuthorizationException('Staff member is not assigned to a saloon.');
        }

        TenantScope::ensureSaloonAccess($actor, (int) $staff->saloon_id);

        if ($actor->grantsAllPermissions() || $actor->is_system_admin) {
            return;
        }

        EnsuresPermission::one($actor, $permission);
        BranchScope::ensureAssigned($actor);
        BranchScope::ensureSameBranch(
            $actor,
            $staff->branch_id !== null ? (int) $staff->branch_id : null,
            'You can only manage staff within your current branch.',
        );
    }

    public static function resolveSaloonId(User $actor, ?int $requestedSaloonId): int
    {
        return TenantScope::resolveSaloonId($actor, $requestedSaloonId);
    }
}
