<?php

namespace App\Actions\Auth;

use App\Models\Role;
use App\Models\Saloon;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Repositories\Contracts\SaloonRepositoryInterface;
use App\Repositories\Contracts\UserRepositoryInterface;
use App\Services\Affiliate\AffiliateCommissionService;
use App\Services\Saloon\SalonReferralService;
use App\Support\Role\RoleCodes;
use App\Support\Subscription\SubscriptionEntitlements;
use Illuminate\Support\Facades\DB;

class RegisterSalonOwnerAction
{
    public function __construct(
        private readonly SaloonRepositoryInterface $saloonRepository,
        private readonly UserRepositoryInterface $userRepository,
        private readonly SubscriptionEntitlements $entitlements,
        private readonly AffiliateCommissionService $affiliateCommissionService,
        private readonly SalonReferralService $salonReferralService,
    ) {
    }

    /**
     * @param array<string, mixed> $payload
     * @return array{user: User, saloon: Saloon}
     */
    public function execute(array $payload): array
    {
        return DB::transaction(function () use ($payload): array {
            $saloon = $this->saloonRepository->create([
                'name' => trim((string) $payload['salon_name']),
                'is_active' => true,
                'activation_status' => Saloon::ACTIVATION_ACTIVE,
            ]);

            $ownerRole = Role::findByCode(RoleCodes::SALON_FRANCHISE_OWNER);

            if ($ownerRole === null) {
                throw new \RuntimeException('Salon franchise owner role is not configured. Run RoleSeeder.');
            }

            $user = $this->userRepository->create([
                'name' => trim((string) $payload['name']),
                'email' => strtolower(trim((string) $payload['email'])),
                'phone' => trim((string) $payload['phone']),
                'password' => (string) $payload['password'],
                'role_id' => $ownerRole->id,
                'saloon_id' => $saloon->id,
            ]);

            $trialPlan = SubscriptionPlan::query()->where('slug', 'free')->first()
                ?? SubscriptionPlan::query()->where('slug', 'free-trial')->first();
            if ($trialPlan !== null) {
                $this->entitlements->assignPlan($saloon, $trialPlan, startTrial: true);
            }

            $this->salonReferralService->ensureReferralCode($saloon->fresh());

            $referrerSaloon = $this->salonReferralService->findReferrerByCode($payload['referral_code'] ?? null);

            if ($referrerSaloon !== null) {
                $this->salonReferralService->attachReferrer(
                    $saloon->fresh(),
                    $referrerSaloon,
                    referralCode: strtoupper(trim((string) ($payload['referral_code'] ?? $referrerSaloon->referral_code))),
                    source: 'link',
                    metadata: [
                        'channel' => 'public_register',
                        'owner_email' => $user->email,
                    ],
                );
            }

            $affiliate = $this->affiliateCommissionService->findActiveAffiliateByCode($payload['affiliate_code'] ?? null);

            if ($affiliate !== null) {
                $this->affiliateCommissionService->attachAffiliateToSaloon(
                    $saloon,
                    $affiliate,
                    referralCode: strtoupper(trim((string) ($payload['affiliate_code'] ?? $affiliate->code))),
                    source: 'link',
                    metadata: [
                        'channel' => 'public_register',
                        'owner_email' => $user->email,
                    ],
                );
                $this->affiliateCommissionService->recordOnboardingCommission(
                    $saloon,
                    baseAmount: (float) ($trialPlan?->price ?? 0),
                    currency: 'INR',
                    notes: 'Commission generated when a referred salon owner registered.',
                );
            }

            return [
                'user' => $user,
                'saloon' => $saloon,
            ];
        });
    }
}
