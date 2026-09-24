<?php

namespace App\Actions\Admin;

use App\Models\AffiliatePartner;
use App\Models\AffiliateReferral;
use App\Models\Category;
use App\Mail\SalonWelcomeMail;
use App\Models\Role;
use App\Support\Role\RoleCodes;
use App\Models\SalonServiceProduct;
use App\Models\Saloon;
use App\Models\SaloonBranch;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Services\Affiliate\AffiliateCommissionService;
use App\Services\Tenant\TenantNotificationDispatcher;
use App\Support\Database\WriteRetry;
use App\Support\Saloon\ReferralCodeGenerator;
use App\Support\Staff\DefaultStaffProvisioner;
use App\Support\Subscription\SubscriptionEntitlements;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

class AdminOnboardingAction
{
    public function __construct(
        private readonly SubscriptionEntitlements $entitlements,
        private readonly AffiliateCommissionService $affiliateCommissionService,
        private readonly DefaultStaffProvisioner $defaultStaffProvisioner,
        private readonly TenantNotificationDispatcher $notifications,
    ) {
    }

    /**
     * @param array<string, mixed> $payload
     * @return array{saloon: Saloon, branch: SaloonBranch, user: User, service_products: array<int, SalonServiceProduct>, temporary_password: string}
     */
    public function execute(array $payload): array
    {
        $result = DB::transaction(function () use ($payload): array {
            $saloonOwnerRole = Role::findByCode(RoleCodes::SALON_FRANCHISE_OWNER);

            if (! $saloonOwnerRole) {
                throw new RuntimeException('Salon franchise owner role not found. Run RoleSeeder.');
            }

            $saloonData = $payload['saloon'];

            $saloon = Saloon::query()->create([
                'name' => trim((string) $saloonData['business_name']),
                'payment_type' => $saloonData['payment_type'],
                'payment_amount' => $saloonData['payment_amount'],
                'transaction_id' => $saloonData['transaction_id'] ?? null,
                'is_active' => (bool) $saloonData['is_active'],
                'activation_status' => (bool) $saloonData['is_active']
                    ? Saloon::ACTIVATION_ACTIVE
                    : Saloon::ACTIVATION_PENDING,
                'referral_code' => ReferralCodeGenerator::generate(
                    trim((string) $saloonData['business_name']),
                ),
            ]);

            $plan = null;
            if (! empty($payload['subscription_plan_id'])) {
                $plan = SubscriptionPlan::query()->find((int) $payload['subscription_plan_id']);
            } elseif (! empty($payload['subscription_plan_slug'])) {
                $plan = SubscriptionPlan::query()->where('slug', (string) $payload['subscription_plan_slug'])->first();
            }

            if ($plan === null) {
                $plan = SubscriptionPlan::query()->where('slug', 'free')->first()
                    ?? SubscriptionPlan::query()->where('slug', 'basic')->first();
            }

            if ($plan !== null) {
                $startTrial = array_key_exists('start_trial', $payload)
                    ? (bool) $payload['start_trial']
                    : ((int) $plan->trial_days > 0);
                $trialDays = array_key_exists('trial_days', $payload)
                    ? (int) $payload['trial_days']
                    : null;

                $this->entitlements->assignPlan(
                    $saloon,
                    $plan,
                    startTrial: $startTrial,
                    trialDays: $trialDays,
                );
            }

            $branchData = $payload['branch'];

            $this->entitlements->ensureCanAddBranch($saloon);

            $branch = SaloonBranch::query()->create([
                'saloon_id' => $saloon->id,
                'branch_name' => trim((string) $branchData['branch_name']),
                'business_address_1' => trim((string) $branchData['business_address_1']),
                'business_address_2' => isset($branchData['business_address_2'])
                    ? trim((string) $branchData['business_address_2'])
                    : null,
                'city' => trim((string) $branchData['city']),
                'state' => trim((string) $branchData['state']),
                'area_pincode' => trim((string) $branchData['area_pincode']),
                'country' => trim((string) ($branchData['country'] ?? 'India')),
                'is_active' => (bool) $branchData['is_active'],
            ]);

            $userData = $payload['user'];
            $firstname = trim((string) $userData['firstname']);
            $lastname = trim((string) $userData['lastname']);

            $ownerPasswordProvided = ! empty($userData['password']);
            $ownerTemporaryPassword = $ownerPasswordProvided
                ? (string) $userData['password']
                : Str::password(12);

            $userAttributes = [
                'name' => trim("{$firstname} {$lastname}"),
                'firstname' => $firstname,
                'lastname' => $lastname,
                'email' => strtolower(trim((string) $userData['email'])),
                'phone' => trim((string) $userData['phone']),
                'whatsapp' => filled($userData['whatsapp'] ?? null)
                    ? trim((string) $userData['whatsapp'])
                    : null,
                'saloon_id' => $saloon->id,
                'branch_id' => $branch->id,
                'role_id' => $saloonOwnerRole->id,
                'is_active' => (bool) $userData['is_active'],
                'onboarding_completed_at' => now(),
            ];

            // Always set an initial password so the owner can immediately login.
            $userAttributes['password'] = $ownerTemporaryPassword;

            $user = User::query()->create($userAttributes);

            $this->defaultStaffProvisioner->ensureDefaultStaff(
                $saloon,
                $branch,
                password: $ownerPasswordProvided ? $ownerTemporaryPassword : null,
            );

            $serviceProducts = [];

            foreach ($payload['service_products'] ?? [] as $serviceProductData) {
                $serviceProducts[] = SalonServiceProduct::query()->create([
                    'saloon_id' => $saloon->id,
                    'branch_id' => $branch->id,
                    'service_id' => $this->resolveServiceId($serviceProductData),
                    'product_id' => $this->resolveProductId($serviceProductData),
                    'price' => $serviceProductData['price'],
                    'duration_minutes' => (int) $serviceProductData['duration_minutes'],
                    'is_active' => (bool) $serviceProductData['is_active'],
                ]);
            }

            $this->syncAffiliateAttribution(
                $saloon->fresh(),
                $payload['affiliate_partner_id'] ?? null,
                (float) ($saloonData['payment_amount'] ?? 0),
                recordCommission: true,
            );

            return [
                'saloon' => $saloon->fresh(['affiliatePartner']),
                'branch' => $branch,
                'user' => $user->load('role'),
                'service_products' => $serviceProducts,
                'temporary_password' => $ownerTemporaryPassword,
            ];
        });

        $this->notifications->sendMail(
            $result['saloon'],
            $result['user']->email,
            new SalonWelcomeMail($result['user'], $result['saloon'], $result['temporary_password']),
        );

        return $result;
    }

    /**
     * @param array<string, mixed> $payload
     * @return array{saloon: Saloon, branch: SaloonBranch, user: User, service_products: array<int, SalonServiceProduct>}
     */
    public function update(Saloon $saloon, array $payload): array
    {
        return DB::transaction(function () use ($saloon, $payload): array {
            $saloonOwnerRole = Role::findByCode(RoleCodes::SALON_FRANCHISE_OWNER);

            if (! $saloonOwnerRole) {
                throw new RuntimeException('Salon franchise owner role not found. Run RoleSeeder.');
            }

            $saloonData = $payload['saloon'];
            $saloon->update([
                'name' => trim((string) $saloonData['business_name']),
                'payment_type' => $saloonData['payment_type'],
                'payment_amount' => $saloonData['payment_amount'],
                'transaction_id' => $saloonData['transaction_id'] ?? null,
                'is_active' => (bool) $saloonData['is_active'],
                'activation_status' => (bool) $saloonData['is_active']
                    ? Saloon::ACTIVATION_ACTIVE
                    : Saloon::ACTIVATION_PENDING,
                'referral_code' => isset($saloonData['referral_code'])
                    ? strtoupper(trim((string) $saloonData['referral_code']))
                    : null,
            ]);

            $branchData = $payload['branch'];
            $branchAttributes = [
                'saloon_id' => $saloon->id,
                'branch_name' => trim((string) $branchData['branch_name']),
                'business_address_1' => trim((string) $branchData['business_address_1']),
                'business_address_2' => isset($branchData['business_address_2'])
                    ? trim((string) $branchData['business_address_2'])
                    : null,
                'city' => trim((string) $branchData['city']),
                'state' => trim((string) $branchData['state']),
                'area_pincode' => trim((string) $branchData['area_pincode']),
                'country' => trim((string) ($branchData['country'] ?? 'India')),
                'is_active' => (bool) $branchData['is_active'],
            ];

            if (! empty($branchData['id'])) {
                $branch = SaloonBranch::query()
                    ->where('saloon_id', $saloon->id)
                    ->whereKey($branchData['id'])
                    ->firstOrFail();
                $branch->update($branchAttributes);
            } else {
                $branch = SaloonBranch::query()->create($branchAttributes);
            }

            $userData = $payload['user'];
            $firstname = trim((string) $userData['firstname']);
            $lastname = trim((string) $userData['lastname']);

            $userAttributes = [
                'name' => trim("{$firstname} {$lastname}"),
                'firstname' => $firstname,
                'lastname' => $lastname,
                'email' => strtolower(trim((string) $userData['email'])),
                'phone' => trim((string) $userData['phone']),
                'whatsapp' => filled($userData['whatsapp'] ?? null)
                    ? trim((string) $userData['whatsapp'])
                    : null,
                'saloon_id' => $saloon->id,
                'branch_id' => $branch->id,
                'role_id' => $saloonOwnerRole->id,
                'is_active' => (bool) $userData['is_active'],
            ];

            if (! empty($userData['password'])) {
                $userAttributes['password'] = (string) $userData['password'];
            }

            if (! empty($userData['id'])) {
                $user = User::query()
                    ->where('saloon_id', $saloon->id)
                    ->whereKey($userData['id'])
                    ->firstOrFail();
                $user->update($userAttributes);
            } else {
                $user = User::query()->create($userAttributes);
            }

            $serviceProducts = $this->syncServiceProducts($saloon, $payload['service_products'] ?? []);

            $this->syncSubscriptionPlan($saloon, $payload);

            if (array_key_exists('affiliate_partner_id', $payload)) {
                $this->syncAffiliateAttribution(
                    $saloon->fresh(),
                    $payload['affiliate_partner_id'],
                    (float) ($saloonData['payment_amount'] ?? 0),
                    recordCommission: $saloon->affiliate_partner_id === null && $payload['affiliate_partner_id'] !== null,
                );
            }

            $this->defaultStaffProvisioner->ensureDefaultStaff($saloon, $branch->fresh());

            return [
                'saloon' => $saloon->fresh(['affiliatePartner']),
                'branch' => $branch->fresh(),
                'user' => $user->fresh()->load('role'),
                'service_products' => $serviceProducts,
            ];
        });
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function syncSubscriptionPlan(Saloon $saloon, array $payload): void
    {
        if (empty($payload['subscription_plan_id']) && empty($payload['subscription_plan_slug'])) {
            return;
        }

        $plan = null;
        if (! empty($payload['subscription_plan_id'])) {
            $plan = SubscriptionPlan::query()->find((int) $payload['subscription_plan_id']);
        } elseif (! empty($payload['subscription_plan_slug'])) {
            $plan = SubscriptionPlan::query()->where('slug', (string) $payload['subscription_plan_slug'])->first();
        }

        if ($plan === null) {
            return;
        }

        $startTrial = array_key_exists('start_trial', $payload)
            ? (bool) $payload['start_trial']
            : ((int) $plan->trial_days > 0);
        $trialDays = array_key_exists('trial_days', $payload)
            ? (int) $payload['trial_days']
            : null;

        $this->entitlements->assignPlan(
            $saloon,
            $plan,
            startTrial: $startTrial,
            trialDays: $trialDays,
        );
    }

    private function syncAffiliateAttribution(
        Saloon $saloon,
        mixed $affiliatePartnerId,
        float $paymentAmount,
        bool $recordCommission,
    ): void {
        if ($affiliatePartnerId === null || $affiliatePartnerId === '') {
            if ($saloon->affiliate_partner_id !== null) {
                $saloon->update([
                    'affiliate_partner_id' => null,
                    'affiliate_referral_code' => null,
                    'affiliate_attributed_at' => null,
                ]);
                AffiliateReferral::query()->where('saloon_id', $saloon->id)->delete();
            }

            return;
        }

        $affiliate = AffiliatePartner::query()
            ->whereKey((int) $affiliatePartnerId)
            ->where('status', AffiliatePartner::STATUS_ACTIVE)
            ->first();

        if ($affiliate === null) {
            return;
        }

        $this->affiliateCommissionService->attachAffiliateToSaloon(
            $saloon,
            $affiliate,
            referralCode: $affiliate->code,
            source: AffiliateReferral::SOURCE_ADMIN,
            metadata: [
                'channel' => 'admin_onboarding',
            ],
        );

        if ($recordCommission) {
            $this->affiliateCommissionService->recordOnboardingCommission(
                $saloon->fresh(['affiliatePartner', 'affiliateReferral']),
                baseAmount: $paymentAmount,
                currency: 'INR',
                notes: 'Commission generated from admin salon onboarding.',
            );
        }
    }

    /**
     * @param array<int, array<string, mixed>> $serviceProductRows
     * @return array<int, SalonServiceProduct>
     */
    private function syncServiceProducts(Saloon $saloon, array $serviceProductRows): array
    {
        $keptIds = collect($serviceProductRows)
            ->pluck('id')
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->values();

        SalonServiceProduct::query()
            ->where('saloon_id', $saloon->id)
            ->when($keptIds->isNotEmpty(), fn ($query) => $query->whereNotIn('id', $keptIds))
            ->delete();

        $serviceProducts = [];

        foreach ($serviceProductRows as $serviceProductData) {
            $serviceId = $this->resolveServiceId($serviceProductData);
            $productId = $this->resolveProductId($serviceProductData);

            $attributes = [
                'saloon_id' => $saloon->id,
                'branch_id' => $serviceProductData['branch_id'] ?? $saloon->branches()->orderBy('id')->value('id'),
                'service_id' => $serviceId,
                'product_id' => $productId,
                'price' => $serviceProductData['price'],
                'duration_minutes' => (int) $serviceProductData['duration_minutes'],
                'is_active' => (bool) $serviceProductData['is_active'],
            ];

            if (! empty($serviceProductData['id'])) {
                $record = SalonServiceProduct::query()
                    ->where('saloon_id', $saloon->id)
                    ->whereKey($serviceProductData['id'])
                    ->firstOrFail();
                $record->update($attributes);
                $serviceProducts[] = $record->fresh();
                continue;
            }

            $serviceProducts[] = SalonServiceProduct::query()->create($attributes);
        }

        return $serviceProducts;
    }

    /**
     * @param array<string, mixed> $serviceProductData
     */
    private function resolveServiceId(array $serviceProductData): int
    {
        if (isset($serviceProductData['service_id'])) {
            return (int) $serviceProductData['service_id'];
        }

        $serviceName = trim((string) $serviceProductData['service_name']);
        $categoryId = $this->resolveCategoryId($serviceProductData);
        $service = \App\Models\Service::query()->firstOrCreate(
            ['name' => $serviceName],
            [
                'category_id' => $categoryId,
                'default_price' => $serviceProductData['price'],
                'duration_minutes' => (int) $serviceProductData['duration_minutes'],
                'is_active' => true,
            ],
        );
        if ($service->category_id === null && $categoryId !== null) {
            $service->update(['category_id' => $categoryId]);
        }

        return $service->id;
    }

    /**
     * @param array<string, mixed> $serviceProductData
     */
    private function resolveProductId(array $serviceProductData): ?int
    {
        if (array_key_exists('product_id', $serviceProductData) && $serviceProductData['product_id'] !== null && $serviceProductData['product_id'] !== '') {
            return (int) $serviceProductData['product_id'];
        }

        $productName = trim((string) ($serviceProductData['product_name'] ?? ''));
        if ($productName === '') {
            return null;
        }

        $categoryId = $this->resolveCategoryId($serviceProductData);
        $product = \App\Models\Product::query()->firstOrCreate(
            ['name' => $productName],
            [
                'category_id' => $categoryId,
                'is_active' => true,
            ],
        );
        if ($product->category_id === null && $categoryId !== null) {
            $product->update(['category_id' => $categoryId]);
        }

        return $product->id;
    }

    /**
     * @param array<string, mixed> $serviceProductData
     */
    private function resolveCategoryId(array $serviceProductData): ?int
    {
        if (isset($serviceProductData['category_id'])) {
            return (int) $serviceProductData['category_id'];
        }

        $legacyName = trim((string) ($serviceProductData['category_name'] ?? ''));
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
}
