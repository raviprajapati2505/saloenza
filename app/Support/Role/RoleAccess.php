<?php

namespace App\Support\Role;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Support\UserPermissions;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Builder;

class RoleAccess
{
    /**
     * @param Builder<Role> $query
     */
    public static function applyTenantScope(Builder $query, User $user): void
    {
        if ($user->is_system_admin) {
            return;
        }

        if ($user->saloon_id === null) {
            throw new AuthorizationException('Your account is not assigned to a saloon.');
        }

        // Tenant portals only manage salon/branch roles — never platform or affiliate.
        $query->whereIn('scope', [Role::SCOPE_SALON, Role::SCOPE_BRANCH]);

        $query->where(function (Builder $builder) use ($user): void {
            $builder->whereNull('saloon_id')
                ->orWhere('saloon_id', $user->saloon_id);
        });

        $user->loadMissing('role');
        if ($user->isBranchScopedActor()) {
            if ($user->branch_id === null) {
                throw new AuthorizationException('Your account is not assigned to a branch.');
            }

            $query->where(function (Builder $builder) use ($user): void {
                $builder
                    ->where(function (Builder $system): void {
                        $system->whereNull('saloon_id')
                            ->where('scope', Role::SCOPE_BRANCH);
                    })
                    ->orWhere(function (Builder $custom) use ($user): void {
                        $custom->where('saloon_id', $user->saloon_id)
                            ->where('branch_id', $user->branch_id);
                    });
            });
        }
    }

    public static function ensureAccessible(User $actor, Role $role): void
    {
        if ($actor->is_system_admin) {
            return;
        }

        if ($role->isPlatformScoped()) {
            throw new AuthorizationException('Platform roles cannot be accessed from a tenant account.');
        }

        if ($role->isAffiliateScoped() || $role->code === RoleCodes::AFFILIATE_PARTNER) {
            throw new AuthorizationException('Affiliate roles cannot be accessed from a tenant account.');
        }

        if ($actor->saloon_id === null) {
            throw new AuthorizationException('Your account is not assigned to a saloon.');
        }

        if ($role->saloon_id !== null && (int) $role->saloon_id !== (int) $actor->saloon_id) {
            throw new AuthorizationException('You cannot access roles outside your saloon.');
        }

        $actor->loadMissing('role');
        if ($actor->isBranchScopedActor()) {
            if ($actor->branch_id === null) {
                throw new AuthorizationException('Your account is not assigned to a branch.');
            }

            $isSystemBranchRole = $role->saloon_id === null && $role->isBranchScoped();
            $isOwnBranchRole = $role->saloon_id !== null
                && $role->branch_id !== null
                && (int) $role->branch_id === (int) $actor->branch_id;

            if (! $isSystemBranchRole && ! $isOwnBranchRole) {
                throw new AuthorizationException('You can only access roles for your current branch.');
            }
        }
    }

    public static function ensureAssignableToStaff(User $actor, Role $role): void
    {
        self::ensureAccessible($actor, $role);

        if ($actor->grantsAllPermissions() || $actor->is_system_admin) {
            return;
        }

        $actor->loadMissing('role');
        $actorLevel = (int) ($actor->role?->hierarchy_level ?? 0);
        $roleLevel = (int) $role->hierarchy_level;

        if ($roleLevel < $actorLevel) {
            throw new AuthorizationException('You cannot assign a role with higher privileges than your own.');
        }
    }

    public static function ensureMutable(User $actor, Role $role): void
    {
        self::ensureAccessible($actor, $role);

        if ($role->is_system && ! $actor->is_system_admin) {
            throw new AuthorizationException('System roles cannot be modified.');
        }
    }

    public static function ensureDeletable(User $actor, Role $role): void
    {
        self::ensureMutable($actor, $role);

        if ($role->is_system) {
            throw new AuthorizationException('System roles cannot be deleted.');
        }
    }

    public static function ensurePermissionsAssignable(User $actor, Role $role): void
    {
        self::ensureAccessible($actor, $role);

        if ($role->is_system && ! $actor->is_system_admin) {
            throw new AuthorizationException('System role permissions cannot be changed.');
        }

        if ($actor->grantsAllPermissions() || $actor->is_system_admin) {
            return;
        }

        $actor->loadMissing('role');
        $actorLevel = (int) ($actor->role?->hierarchy_level ?? 0);
        if ((int) $role->hierarchy_level <= $actorLevel) {
            throw new AuthorizationException('You can only assign permissions to roles below your hierarchy level.');
        }
    }

    /**
     * @param list<int|string> $permissionIds
     * @return list<string>
     */
    public static function filterAssignablePermissionIds(User $actor, array $permissionIds): array
    {
        if ($permissionIds === []) {
            return [];
        }

        $ids = array_values(array_unique(array_map('strval', $permissionIds)));
        $query = Permission::query()->whereIn('id', $ids);

        if ($actor->is_system_admin || $actor->grantsAllPermissions()) {
            return $query->pluck('id')->map(fn ($id) => (string) $id)->all();
        }

        $query->where('scope', 'tenant');

        $grantedIds = Permission::query()
            ->whereIn('code', UserPermissions::codesFor($actor)->all())
            ->pluck('id')
            ->map(fn ($id) => (string) $id)
            ->all();

        if ($grantedIds === []) {
            return [];
        }

        return $query->whereIn('id', $grantedIds)
            ->pluck('id')
            ->map(fn ($id) => (string) $id)
            ->all();
    }

    public static function resolveSaloonId(User $actor, ?int $requestedSaloonId): ?int
    {
        if ($actor->is_system_admin) {
            return $requestedSaloonId;
        }

        if ($actor->saloon_id === null) {
            throw new AuthorizationException('Your account is not assigned to a saloon.');
        }

        if ($requestedSaloonId !== null && (int) $requestedSaloonId !== (int) $actor->saloon_id) {
            throw new AuthorizationException('You can only manage roles within your current saloon.');
        }

        return (int) $actor->saloon_id;
    }

    public static function resolveScope(User $actor, ?string $requestedScope): string
    {
        $actor->loadMissing('role');

        if ($actor->isBranchScopedActor()) {
            return Role::SCOPE_BRANCH;
        }

        if (in_array($requestedScope, [Role::SCOPE_SALON, Role::SCOPE_BRANCH], true)) {
            return $requestedScope;
        }

        return Role::SCOPE_SALON;
    }

    public static function resolveBranchId(User $actor, ?int $requestedBranchId): ?int
    {
        $actor->loadMissing('role');

        if ($actor->isBranchScopedActor()) {
            if ($actor->branch_id === null) {
                throw new AuthorizationException('Your account is not assigned to a branch.');
            }

            return (int) $actor->branch_id;
        }

        return $requestedBranchId;
    }

    public static function normalizeHierarchyLevel(User $actor, ?int $requestedLevel): int
    {
        if ($actor->is_system_admin || $actor->grantsAllPermissions()) {
            return $requestedLevel ?? 50;
        }

        $actor->loadMissing('role');
        $minimumLevel = (int) ($actor->role?->hierarchy_level ?? 0) + 1;
        $level = $requestedLevel ?? max($minimumLevel, 50);

        // Never allow equal/higher privilege than the actor; bump to a safe subordinate level.
        return max($level, $minimumLevel);
    }
}
