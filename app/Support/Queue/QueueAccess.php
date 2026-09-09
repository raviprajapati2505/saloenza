<?php

namespace App\Support\Queue;

use App\Models\User;
use App\Support\UserPermissions;

final class QueueAccess
{
    public static function canViewAll(User $actor): bool
    {
        if ($actor->grantsAllPermissions()) {
            return true;
        }

        return UserPermissions::allows($actor, 'queue.view_all');
    }

    /**
     * When non-null, the live queue is limited to appointments assigned to this staff member.
     */
    public static function staffFilterId(User $actor): ?int
    {
        if (self::canViewAll($actor)) {
            return null;
        }

        return (int) $actor->id;
    }

    /**
     * @return array{mode: 'staff'|'branch', staff_id: int|null, label: string}
     */
    public static function scopeMeta(User $actor): array
    {
        $staffFilterId = self::staffFilterId($actor);

        if ($staffFilterId === null) {
            return [
                'mode' => 'branch',
                'staff_id' => null,
                'label' => 'Branch floor queue',
            ];
        }

        return [
            'mode' => 'staff',
            'staff_id' => $staffFilterId,
            'label' => 'Your assigned queue',
        ];
    }
}
