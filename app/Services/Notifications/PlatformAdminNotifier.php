<?php

namespace App\Services\Notifications;

use App\Models\Role;
use App\Models\User;
use App\Notifications\PlatformAdminAlertNotification;
use App\Support\Role\RoleCodes;
use App\Support\UserPermissions;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

class PlatformAdminNotifier
{
    /**
     * @param  list<string>  $requiredPermissions  Recipients must have any of these (system admins always included).
     * @param  array<string, mixed>  $meta
     */
    public function notify(
        string $event,
        string $title,
        string $body,
        string $actionUrl,
        array $requiredPermissions = [],
        array $meta = [],
    ): void {
        $admins = $this->platformAdmins($requiredPermissions);

        if ($admins->isEmpty()) {
            return;
        }

        $send = function () use ($admins, $event, $title, $body, $actionUrl, $meta): void {
            Notification::send(
                $admins,
                new PlatformAdminAlertNotification($event, $title, $body, $actionUrl, $meta),
            );
        };

        // In HTTP requests mid-transaction, wait until commit.
        // In tests, send immediately so RefreshDatabase isolation stays correct.
        if (! app()->runningUnitTests() && DB::transactionLevel() > 0) {
            DB::afterCommit($send);

            return;
        }

        $send();
    }

    /**
     * @param  list<string>  $requiredPermissions
     * @return Collection<int, User>
     */
    public function platformAdmins(array $requiredPermissions = []): Collection
    {
        $platformRoleId = Role::query()
            ->where('code', RoleCodes::PLATFORM_SUPER_ADMIN)
            ->value('id');

        $admins = User::query()
            ->with('role.permissions')
            ->where('is_active', true)
            ->where(function ($query) use ($platformRoleId): void {
                $query->where('is_system_admin', true);

                if ($platformRoleId !== null) {
                    $query->orWhere('role_id', $platformRoleId);
                }

                $query->orWhereHas('role', function ($roleQuery): void {
                    $roleQuery->where('scope', Role::SCOPE_PLATFORM);
                });
            })
            ->get()
            ->unique('id')
            ->values();

        if ($requiredPermissions === []) {
            return $admins;
        }

        return $admins
            ->filter(fn (User $user): bool => UserPermissions::allowsAny($user, $requiredPermissions))
            ->values();
    }
}
