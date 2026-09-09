<?php

namespace App\Services\Pos;

use App\Models\Service;
use App\Models\User;
use App\Services\Inventory\RetailProductService;
use App\Support\Catalog\SalonOfferingResolver;
use App\Support\Pos\PosCatalog;
use App\Support\Role\RoleCodes;
use App\Support\Tenant\TenantScope;

class PosCatalogService
{
    public function __construct(
        private readonly RetailProductService $retail,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function catalog(User $user, ?int $branchId): array
    {
        $saloonId = TenantScope::resolveSaloonFilter($user, null);

        // Any actor with a home branch should get that branch's counter stock without
        // passing it explicitly, the way the inventory screens already resolve a branch.
        if ($branchId === null && $user->branch_id) {
            $branchId = (int) $user->branch_id;
        }

        $retailServiceId = $this->retailServiceId();

        $services = SalonOfferingResolver::bookableServices(
            (int) $saloonId,
            $branchId,
            $retailServiceId > 0 ? $retailServiceId : null,
        );

        // Anything stocked at the branch with a retail price is sellable on its own,
        // so the counter no longer depends on a service/product catalog entry.
        $retail = $branchId !== null && $saloonId !== null
            ? $this->retail->catalogForBranch($saloonId, $branchId)
            : collect();

        $staffQuery = User::query()
            ->staff()
            ->when($saloonId !== null, fn ($query) => $query->where('saloon_id', $saloonId))
            ->when($branchId !== null, fn ($query) => $query->where('branch_id', $branchId))
            ->where('is_active', true)
            ->orderBy('name');

        if ($user->hasRoleCode(RoleCodes::SALON_STAFF)) {
            $staffQuery->where('id', $user->id);
        }

        return [
            'branch_id' => $branchId,
            'services' => $services,
            'retail_products' => $retail->all(),
            'staff' => $staffQuery->get(['id', 'name', 'branch_id'])->map(fn (User $member): array => [
                'id' => $member->id,
                'name' => $member->name,
                'branch_id' => $member->branch_id,
            ])->all(),
            'defaults' => [
                'branch_id' => $user->branch_id,
                'staff_id' => $user->hasRoleCode(RoleCodes::SALON_STAFF) ? $user->id : null,
                'lock_branch' => $user->isBranchScopedActor() && (bool) $user->branch_id,
                'lock_staff' => $user->hasRoleCode(RoleCodes::SALON_STAFF),
            ],
        ];
    }

    public function retailServiceId(): int
    {
        $service = Service::query()
            ->where('name', PosCatalog::RETAIL_SERVICE_NAME)
            ->first();

        return (int) ($service?->id ?? 0);
    }
}
