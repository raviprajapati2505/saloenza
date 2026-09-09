<?php

namespace App\Actions\Onboarding;

use App\Models\Category;
use App\Models\Product;
use App\Models\SalonServiceProduct;
use App\Models\SaloonBranch;
use App\Models\Service;
use App\Models\User;
use App\Repositories\Contracts\SaloonRepositoryInterface;
use App\Repositories\Contracts\UserRepositoryInterface;
use App\Services\Subscription\SubscriptionUpgradeService;
use App\Support\Database\WriteRetry;
use App\Support\Staff\DefaultStaffProvisioner;
use App\Support\Subscription\SubscriptionEntitlements;
use Illuminate\Auth\Access\AuthorizationException;

class OnboardingAction
{
    public function __construct(
        private readonly UserRepositoryInterface $userRepository,
        private readonly SaloonRepositoryInterface $saloonRepository,
        private readonly SubscriptionEntitlements $entitlements,
        private readonly DefaultStaffProvisioner $defaultStaffProvisioner,
        private readonly SubscriptionUpgradeService $upgradeService,
    ) {
    }

    /**
     * @param array<string, mixed> $payload
     */
    public function saveAccount(User $user, array $payload): void
    {
        $this->userRepository->update($user, [
            'name' => trim((string) $payload['name']),
        ]);

        $user->loadMissing('saloon');

        if ($user->saloon === null) {
            return;
        }

        $plan = null;

        if (! empty($payload['subscription_plan_id'])) {
            $plan = $this->entitlements->findPublicPlanById((int) $payload['subscription_plan_id']);
        } elseif (! empty($payload['plan_slug'])) {
            $plan = $this->entitlements->findPublicPlanBySlug((string) $payload['plan_slug']);
        } elseif (! empty($payload['plan'])) {
            $plan = $this->entitlements->findPublicPlanBySlug(strtolower(str_replace(' ', '-', trim((string) $payload['plan']))));
        }

        if ($plan === null) {
            $plan = $this->entitlements->findPublicPlanBySlug('free');
            if ($plan === null) {
                $plan = $this->entitlements->findPublicPlanBySlug('free-trial');
            }
        }

        if ($plan === null) {
            return;
        }

        if ($plan->isPaidPlan()) {
            $this->upgradeService->requestPaidPlanActivation($user, $user->saloon, $plan);

            return;
        }

        $active = $this->entitlements->activeSubscription($user->saloon);
        $activePlanId = $active?->subscription_plan_id;

        if ($active === null || (int) $activePlanId !== (int) $plan->id) {
            $this->entitlements->assignPlan(
                $user->saloon,
                $plan,
                startTrial: $plan->trial_days > 0,
            );
        }

        $user->saloon->markActivated();
    }

    /**
     * @param array<string, mixed> $payload
     */
    public function saveBranch(User $user, array $payload): void
    {
        $user->loadMissing('saloon');

        if ($user->saloon === null) {
            throw new AuthorizationException('Your account is not linked to a saloon.');
        }

        $this->saloonRepository->update($user->saloon, [
            'branch_name' => trim((string) $payload['branchName']),
            'address' => trim((string) $payload['address']),
            'city' => trim((string) $payload['city']),
            'state' => trim((string) $payload['state']),
            'phone' => trim((string) $payload['phone']),
            'whatsapp' => isset($payload['whatsapp']) ? trim((string) $payload['whatsapp']) : null,
            'gst_number' => isset($payload['gstNumber']) ? strtoupper(trim((string) $payload['gstNumber'])) : null,
            'working_hours' => $payload['hours'],
        ]);

        $branch = SaloonBranch::query()
            ->where('saloon_id', $user->saloon->id)
            ->orderBy('id')
            ->first();

        if ($branch === null) {
            $this->entitlements->ensureCanAddBranch($user->saloon);

            $branch = SaloonBranch::query()->create([
                'saloon_id' => $user->saloon->id,
                'branch_name' => trim((string) $payload['branchName']),
                'business_address_1' => trim((string) $payload['address']),
                'city' => trim((string) $payload['city']),
                'state' => trim((string) $payload['state']),
                'area_pincode' => trim((string) ($payload['area_pincode'] ?? $payload['pincode'] ?? '000000')),
                'country' => trim((string) ($payload['country'] ?? 'India')),
                'is_active' => true,
            ]);
        }

        $this->defaultStaffProvisioner->ensureDefaultStaff($user->saloon, $branch);
    }

    /**
     * @param array<string, mixed> $payload
     */
    public function saveServices(User $user, array $payload): void
    {
        if ((bool) ($payload['skipped'] ?? false)) {
            return;
        }

        $user->loadMissing('saloon');

        if ($user->saloon === null) {
            throw new AuthorizationException('Your account is not linked to a saloon.');
        }

        $branchId = SaloonBranch::query()
            ->where('saloon_id', $user->saloon->id)
            ->orderBy('id')
            ->value('id');

        foreach ($payload['services'] ?? [] as $serviceData) {
            WriteRetry::run(function () use ($user, $branchId, $serviceData): void {
                $categoryId = isset($serviceData['category_id']) && $serviceData['category_id'] !== '' && $serviceData['category_id'] !== null
                    ? (int) $serviceData['category_id']
                    : $this->resolveCategoryId($serviceData);

                if (isset($serviceData['service_id'])) {
                    $serviceId = (int) $serviceData['service_id'];
                    $service = Service::query()->findOrFail($serviceId);
                } else {
                    $serviceName = trim((string) $serviceData['name']);
                    $service = Service::query()->firstOrCreate(
                        ['name' => $serviceName],
                        [
                            'category_id' => $categoryId,
                            'default_price' => $serviceData['price'],
                            'duration_minutes' => (int) $serviceData['duration'],
                            'is_active' => true,
                        ],
                    );
                    if ($service->category_id === null && $categoryId !== null) {
                        $service->update(['category_id' => $categoryId]);
                    }
                    $serviceId = $service->id;
                }

                $productId = null;
                if (isset($serviceData['product_id']) && $serviceData['product_id'] !== '' && $serviceData['product_id'] !== null) {
                    $productId = (int) $serviceData['product_id'];
                } else {
                    $productName = trim((string) ($serviceData['product'] ?? ''));
                    if ($productName !== '') {
                        $product = Product::query()->firstOrCreate(
                            ['name' => $productName],
                            [
                                'category_id' => $categoryId,
                                'is_active' => true,
                            ],
                        );
                        if ($product->category_id === null && $categoryId !== null) {
                            $product->update(['category_id' => $categoryId]);
                        }
                        $productId = $product->id;
                    }
                }

                SalonServiceProduct::query()->firstOrCreate(
                    [
                        'saloon_id' => $user->saloon->id,
                        'branch_id' => $branchId,
                        'service_id' => $serviceId,
                        'product_id' => $productId,
                    ],
                    [
                        'price' => $serviceData['price'],
                        'duration_minutes' => (int) $serviceData['duration'],
                        'is_active' => true,
                    ],
                );
            });
        }
    }

    /**
     * @param array<string, mixed> $serviceData
     */
    private function resolveCategoryId(array $serviceData): ?int
    {
        if (isset($serviceData['category_id']) && $serviceData['category_id'] !== '' && $serviceData['category_id'] !== null) {
            $id = (int) $serviceData['category_id'];
            if ($id > 0 && Category::query()->whereKey($id)->exists()) {
                return $id;
            }
        }

        $legacyName = trim((string) ($serviceData['category'] ?? $serviceData['category_name'] ?? ''));
        if ($legacyName === '') {
            $fallback = Category::query()->where('is_active', true)->orderBy('id')->value('id');

            return $fallback !== null ? (int) $fallback : null;
        }

        return WriteRetry::run(function () use ($legacyName): int {
            $existing = Category::query()
                ->whereRaw('LOWER(name) = ?', [mb_strtolower($legacyName)])
                ->first();

            if ($existing !== null) {
                return (int) $existing->id;
            }

            return (int) Category::query()->create([
                'name' => $legacyName,
                'is_active' => true,
            ])->id;
        });
    }

    public function complete(User $user): void
    {
        $this->userRepository->update($user, [
            'onboarding_completed_at' => now(),
        ]);
    }
}
