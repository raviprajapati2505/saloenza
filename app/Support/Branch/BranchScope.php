<?php

namespace App\Support\Branch;

use App\Models\SaloonBranch;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;

final class BranchScope
{
    public static function ensureAssigned(User $user): void
    {
        $user->loadMissing('role');

        if (! $user->isBranchScopedActor()) {
            return;
        }

        if ($user->branch_id === null) {
            throw new AuthorizationException('Your account is not assigned to a branch.');
        }
    }

    public static function resolveBranchId(User $user, mixed $requestedBranchId): ?int
    {
        $user->loadMissing('role');

        if ($user->isBranchScopedActor()) {
            self::ensureAssigned($user);

            return (int) $user->branch_id;
        }

        if ($requestedBranchId !== null && $requestedBranchId !== '') {
            return (int) $requestedBranchId;
        }

        if ($user->branch_id !== null) {
            return (int) $user->branch_id;
        }

        if ($user->saloon_id === null) {
            return null;
        }

        return SaloonBranch::query()
            ->where('saloon_id', $user->saloon_id)
            ->orderBy('id')
            ->value('id');
    }

    public static function ensureSameBranch(User $user, ?int $resourceBranchId, string $message): void
    {
        $user->loadMissing('role');

        if (! $user->isBranchScopedActor()) {
            return;
        }

        self::ensureAssigned($user);

        if ((int) $resourceBranchId !== (int) $user->branch_id) {
            throw new AuthorizationException($message);
        }
    }

    public static function ensureStaffBelongsToBranch(?int $staffId, ?int $branchId): void
    {
        if ($staffId === null || $branchId === null) {
            return;
        }

        $staff = User::query()->find($staffId);

        if ($staff === null) {
            throw new AuthorizationException('Selected staff member was not found.');
        }

        if ($staff->branch_id !== null && (int) $staff->branch_id !== (int) $branchId) {
            throw new AuthorizationException('Selected staff member does not belong to this branch.');
        }
    }
}
