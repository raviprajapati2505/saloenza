<?php

namespace App\Actions\Branch;

use App\Models\Role;
use App\Models\SalonServiceProduct;
use App\Models\Saloon;
use App\Models\SaloonBranch;
use App\Models\User;
use App\Support\Role\RoleCodes;
use App\Support\Subscription\SubscriptionEntitlements;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class ProvisionBranchAction
{
    public function __construct(
        private readonly SubscriptionEntitlements $entitlements,
    ) {
    }

    /**
     * @param array<string, mixed> $payload
     * @return array{
     *     branch: SaloonBranch,
     *     manager: User|null,
     *     cloned_catalog_items: int,
     *     cloned_roles: int
     * }
     */
    public function create(Saloon $saloon, array $payload, bool $enforceSubscriptionLimits = true): array
    {
        if ($enforceSubscriptionLimits) {
            $this->entitlements->ensureCanAddBranch($saloon);
        }

        return DB::transaction(function () use ($saloon, $payload): array {
            $branch = SaloonBranch::query()->create([
                'saloon_id' => $saloon->id,
                'branch_name' => trim((string) $payload['branch_name']),
                'business_address_1' => trim((string) $payload['business_address_1']),
                'business_address_2' => $this->normalizeNullableString($payload['business_address_2'] ?? null),
                'city' => trim((string) $payload['city']),
                'state' => trim((string) $payload['state']),
                'area_pincode' => trim((string) $payload['area_pincode']),
                'country' => trim((string) ($payload['country'] ?? 'India')),
                'is_active' => (bool) $payload['is_active'],
            ]);

            $templateBranch = $this->resolveTemplateBranch($saloon, $branch, $payload['template_branch_id'] ?? null);
            $clonedCatalogItems = $this->cloneCatalog($saloon, $branch, $templateBranch);
            $clonedRoles = $this->cloneCustomBranchRoles($saloon, $branch, $templateBranch);
            $manager = $this->createBranchManager($saloon, $branch, $payload['manager'] ?? null);

            return [
                'branch' => $branch->fresh(),
                'manager' => $manager?->fresh(['role', 'branch']),
                'cloned_catalog_items' => $clonedCatalogItems,
                'cloned_roles' => $clonedRoles,
            ];
        });
    }

    /**
     * @param array<string, mixed> $payload
     */
    public function update(SaloonBranch $branch, array $payload): SaloonBranch
    {
        $branch->update([
            'branch_name' => trim((string) $payload['branch_name']),
            'business_address_1' => trim((string) $payload['business_address_1']),
            'business_address_2' => $this->normalizeNullableString($payload['business_address_2'] ?? null),
            'city' => trim((string) $payload['city']),
            'state' => trim((string) $payload['state']),
            'area_pincode' => trim((string) $payload['area_pincode']),
            'country' => trim((string) ($payload['country'] ?? 'India')),
            'is_active' => (bool) $payload['is_active'],
        ]);

        return $branch->fresh();
    }

    private function resolveTemplateBranch(Saloon $saloon, SaloonBranch $branch, mixed $templateBranchId): ?SaloonBranch
    {
        if ($templateBranchId !== null) {
            return SaloonBranch::query()
                ->where('saloon_id', $saloon->id)
                ->whereKey((int) $templateBranchId)
                ->first();
        }

        return SaloonBranch::query()
            ->where('saloon_id', $saloon->id)
            ->whereKeyNot($branch->id)
            ->orderBy('id')
            ->first();
    }

    private function cloneCatalog(Saloon $saloon, SaloonBranch $branch, ?SaloonBranch $templateBranch): int
    {
        $sourceBranchId = $templateBranch?->id;

        $sourceItems = SalonServiceProduct::query()
            ->where('saloon_id', $saloon->id)
            ->when(
                $sourceBranchId !== null,
                fn ($query) => $query->where('branch_id', $sourceBranchId),
                fn ($query) => $query->whereNull('branch_id'),
            )
            ->get();

        foreach ($sourceItems as $item) {
            SalonServiceProduct::query()->updateOrCreate(
                [
                    'saloon_id' => $saloon->id,
                    'branch_id' => $branch->id,
                    'service_id' => $item->service_id,
                    'product_id' => $item->product_id,
                ],
                [
                    'price' => $item->price,
                    'duration_minutes' => $item->duration_minutes,
                    'is_active' => $item->is_active,
                ],
            );
        }

        return $sourceItems->count();
    }

    private function cloneCustomBranchRoles(Saloon $saloon, SaloonBranch $branch, ?SaloonBranch $templateBranch): int
    {
        if ($templateBranch === null) {
            return 0;
        }

        $roles = Role::query()
            ->with('permissions:id')
            ->where('saloon_id', $saloon->id)
            ->where('branch_id', $templateBranch->id)
            ->where('is_system', false)
            ->get();

        foreach ($roles as $role) {
            $clone = Role::query()->create([
                'name' => "{$role->name} ({$branch->branch_name})",
                'code' => $role->code.'.branch_'.$branch->id,
                'is_active' => $role->is_active,
                'is_system' => false,
                'scope' => $role->scope,
                'hierarchy_level' => $role->hierarchy_level,
                'saloon_id' => $saloon->id,
                'branch_id' => $branch->id,
            ]);

            $clone->permissions()->sync($role->permissions->pluck('id')->all());
        }

        return $roles->count();
    }

    /**
     * @param array<string, mixed>|null $managerData
     */
    private function createBranchManager(Saloon $saloon, SaloonBranch $branch, ?array $managerData): ?User
    {
        if ($managerData === null || empty($managerData['email'])) {
            return null;
        }

        $role = Role::findByCode(RoleCodes::SALON_BRANCH_MANAGER);

        if (! $role) {
            throw new RuntimeException('Branch manager role not found. Run RoleSeeder.');
        }

        $firstname = trim((string) $managerData['firstname']);
        $lastname = trim((string) $managerData['lastname']);

        $attributes = [
            'name' => trim("{$firstname} {$lastname}"),
            'firstname' => $firstname,
            'lastname' => $lastname,
            'email' => strtolower(trim((string) $managerData['email'])),
            'phone' => trim((string) $managerData['phone']),
            'saloon_id' => $saloon->id,
            'branch_id' => $branch->id,
            'role_id' => $role->id,
            'is_active' => (bool) ($managerData['is_active'] ?? true),
            'onboarding_completed_at' => now(),
        ];

        if (! empty($managerData['password'])) {
            $attributes['password'] = (string) $managerData['password'];
        }

        return User::query()->create($attributes);
    }

    private function normalizeNullableString(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $normalized = trim((string) $value);

        return $normalized === '' ? null : $normalized;
    }
}
