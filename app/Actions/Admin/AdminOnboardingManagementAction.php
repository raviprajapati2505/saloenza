<?php

namespace App\Actions\Admin;

use App\Models\Role;
use App\Models\SalonServiceProduct;
use App\Models\Saloon;
use App\Models\SaloonBranch;
use App\Models\User;
use App\Support\Role\RoleCodes;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class AdminOnboardingManagementAction
{
    /**
     * @param array<string, mixed> $filters
     * @return Collection<int, array{saloon: Saloon, owner: User|null, onboarding_status: string, branch_count: int}>
     */
    public function list(array $filters = []): Collection
    {
        $ownerRoleId = Role::query()
            ->where('code', RoleCodes::SALON_FRANCHISE_OWNER)
            ->whereNull('saloon_id')
            ->value('id');

        $query = Saloon::query()
            ->with([
                'branches' => fn ($query) => $query->orderBy('id'),
                'affiliatePartner',
            ])
            ->withCount('branches')
            ->latest('id');

        if (array_key_exists('is_active', $filters)) {
            $query->where('is_active', (bool) $filters['is_active']);
        }

        if (! empty($filters['search'])) {
            $search = (string) $filters['search'];
            $query->where(function (Builder $builder) use ($search, $ownerRoleId): void {
                $builder->where('name', 'like', "%{$search}%")
                    ->orWhere('referral_code', 'like', "%{$search}%")
                    ->orWhereHas('users', function (Builder $userQuery) use ($search, $ownerRoleId): void {
                        if ($ownerRoleId !== null) {
                            $userQuery->where('role_id', $ownerRoleId);
                        }
                        $userQuery->where(function (Builder $inner) use ($search): void {
                            $inner->where('name', 'like', "%{$search}%")
                                ->orWhere('email', 'like', "%{$search}%")
                                ->orWhere('phone', 'like', "%{$search}%");
                        });
                    });
            });
        }

        $saloons = $query->get();

        $ownerIds = $ownerRoleId
            ? User::query()->where('role_id', $ownerRoleId)->whereIn('saloon_id', $saloons->pluck('id'))->get()->keyBy('saloon_id')
            : collect();

        $items = $saloons->map(function (Saloon $saloon) use ($ownerIds): array {
            /** @var User|null $owner */
            $owner = $ownerIds->get($saloon->id);

            $status = 'no_owner';
            if ($owner !== null) {
                $status = $owner->onboarding_completed_at ? 'completed' : 'pending';
            }

            return [
                'saloon' => $saloon,
                'owner' => $owner,
                'onboarding_status' => $status,
                'branch_count' => (int) $saloon->branches_count,
            ];
        });

        if (! empty($filters['onboarding_status'])) {
            $items = $items->filter(
                fn (array $item): bool => $item['onboarding_status'] === (string) $filters['onboarding_status'],
            );
        }

        return $items->values();
    }

    /**
     * @return array{saloon: Saloon, branch: SaloonBranch|null, user: User|null, service_products: \Illuminate\Support\Collection<int, SalonServiceProduct>}
     */
    public function show(Saloon $saloon): array
    {
        $ownerRoleId = Role::query()
            ->where('code', RoleCodes::SALON_FRANCHISE_OWNER)
            ->whereNull('saloon_id')
            ->value('id');

        $owner = User::query()
            ->where('saloon_id', $saloon->id)
            ->when($ownerRoleId !== null, fn ($query) => $query->where('role_id', $ownerRoleId))
            ->first();

        $branch = null;
        if ($owner?->branch_id !== null) {
            $branch = SaloonBranch::query()
                ->where('saloon_id', $saloon->id)
                ->whereKey($owner->branch_id)
                ->first();
        }

        $branch ??= SaloonBranch::query()
            ->where('saloon_id', $saloon->id)
            ->orderBy('id')
            ->first();

        $serviceProducts = SalonServiceProduct::query()
            ->where('saloon_id', $saloon->id)
            ->with(['service', 'product'])
            ->orderBy('id')
            ->get();

        return [
            'saloon' => $saloon->loadMissing('affiliatePartner'),
            'branch' => $branch,
            'user' => $owner?->loadMissing('role'),
            'service_products' => $serviceProducts,
        ];
    }

    public function completeOwner(User $owner): User
    {
        $this->ensureSalonOwner($owner);

        $owner->update([
            'onboarding_completed_at' => now(),
        ]);

        return $owner->fresh();
    }

    public function resetOwner(User $owner): User
    {
        $this->ensureSalonOwner($owner);

        $owner->update([
            'onboarding_completed_at' => null,
        ]);

        return $owner->fresh();
    }

    private function ensureSalonOwner(User $owner): void
    {
        $owner->loadMissing('role');

        if (! $owner->hasRoleCode(RoleCodes::SALON_FRANCHISE_OWNER)) {
            abort(422, 'Only salon franchise owner accounts can be managed through onboarding.');
        }
    }
}
