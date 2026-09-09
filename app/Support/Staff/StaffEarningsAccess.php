<?php

namespace App\Support\Staff;

use App\Models\User;
use App\Support\Branch\BranchScope;
use App\Support\EnsuresPermission;
use Illuminate\Validation\ValidationException;

final class StaffEarningsAccess
{
    public static function resolveTargetStaff(User $actor, ?int $requestedStaffId): User
    {
        EnsuresPermission::one($actor, 'staff.earnings.view');

        if ($requestedStaffId === null || $requestedStaffId === (int) $actor->id) {
            return $actor;
        }

        EnsuresPermission::one($actor, 'staff.view');

        $staff = User::query()
            ->staff()
            ->where('id', $requestedStaffId)
            ->first();

        if ($staff === null) {
            throw ValidationException::withMessages([
                'staff_id' => 'Staff member not found.',
            ]);
        }

        if ($actor->saloon_id !== null && (int) $staff->saloon_id !== (int) $actor->saloon_id) {
            throw ValidationException::withMessages([
                'staff_id' => 'Staff member is outside your salon.',
            ]);
        }

        BranchScope::ensureSameBranch(
            $actor,
            $staff->branch_id !== null ? (int) $staff->branch_id : null,
            'You can only view earnings for staff in your branch.',
        );

        return $staff;
    }
}
