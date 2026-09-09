<?php

namespace App\Services\Saloon;

use App\Models\SalonReferral;
use App\Models\SalonReferralCredit;
use App\Models\Saloon;
use App\Models\SaloonSubscription;
use App\Models\SubscriptionPlan;
use App\Services\Platform\PlatformSettingsService;
use App\Support\Saloon\ReferralCodeGenerator;
use Illuminate\Support\Facades\DB;

class SalonReferralService
{
    public function __construct(
        private readonly PlatformSettingsService $platformSettingsService,
    ) {
    }

    public function commissionRate(): float
    {
        return $this->platformSettingsService->salonReferralCommissionRate();
    }

    public function qualifyingMonths(): int
    {
        return $this->platformSettingsService->salonReferralQualifyingMonths();
    }

    public function ensureReferralCode(Saloon $saloon): string
    {
        if ($saloon->referral_code !== null && trim((string) $saloon->referral_code) !== '') {
            return strtoupper(trim((string) $saloon->referral_code));
        }

        $code = ReferralCodeGenerator::generate($saloon->name);
        $saloon->update(['referral_code' => $code]);

        return $code;
    }

    public function findReferrerByCode(?string $code): ?Saloon
    {
        if ($code === null || trim($code) === '') {
            return null;
        }

        return Saloon::query()
            ->where('referral_code', strtoupper(trim($code)))
            ->where('is_active', true)
            ->where('activation_status', Saloon::ACTIVATION_ACTIVE)
            ->first();
    }

    public function attachReferrer(
        Saloon $referredSaloon,
        Saloon $referrerSaloon,
        ?string $referralCode = null,
        string $source = SalonReferral::SOURCE_LINK,
        array $metadata = [],
    ): SalonReferral {
        if ($referrerSaloon->id === $referredSaloon->id) {
            throw new \InvalidArgumentException('A salon cannot refer itself.');
        }

        return DB::transaction(function () use ($referredSaloon, $referrerSaloon, $referralCode, $source, $metadata): SalonReferral {
            $referredSaloon->update([
                'referrer_saloon_id' => $referrerSaloon->id,
                'owner_referred_at' => $referredSaloon->owner_referred_at ?? now(),
            ]);

            return SalonReferral::query()->updateOrCreate(
                ['referred_saloon_id' => $referredSaloon->id],
                [
                    'referrer_saloon_id' => $referrerSaloon->id,
                    'owner_user_id' => $referredSaloon->users()->orderBy('id')->value('id'),
                    'referral_code' => $referralCode ?: $referrerSaloon->referral_code,
                    'source' => $source,
                    'status' => SalonReferral::STATUS_IN_PROGRESS,
                    'metadata' => $metadata,
                    'referred_at' => now(),
                ],
            );
        });
    }

    public function processQualifications(Saloon $referrerSaloon): void
    {
        $referrals = SalonReferral::query()
            ->with(['referredSaloon.activeSubscription.plan', 'credit'])
            ->where('referrer_saloon_id', $referrerSaloon->id)
            ->where('status', SalonReferral::STATUS_IN_PROGRESS)
            ->get();

        foreach ($referrals as $referral) {
            if ($this->shouldQualify($referral)) {
                $this->issueCredit($referral);
            }
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function buildDashboard(Saloon $referrerSaloon): array
    {
        $this->ensureReferralCode($referrerSaloon);
        $this->processQualifications($referrerSaloon);

        $referrerSaloon->refresh();
        $referrals = SalonReferral::query()
            ->with([
                'referredSaloon.activeSubscription.plan',
                'owner',
                'credit',
            ])
            ->where('referrer_saloon_id', $referrerSaloon->id)
            ->latest('referred_at')
            ->get();

        $qualifiedCount = $referrals->where('status', SalonReferral::STATUS_QUALIFIED)->count();
        $inProgressCount = $referrals->where('status', SalonReferral::STATUS_IN_PROGRESS)->count();
        $creditsEarned = (float) SalonReferralCredit::query()
            ->where('referrer_saloon_id', $referrerSaloon->id)
            ->where('status', SalonReferralCredit::STATUS_CREDITED)
            ->sum('credit_amount');

        $code = strtoupper(trim((string) $referrerSaloon->referral_code));

        return [
            'referral_code' => $code,
            'signup_url' => url('/register?ref='.$code),
            'commission_rate' => $this->commissionRate(),
            'qualifying_months' => $this->qualifyingMonths(),
            'summary' => [
                'total_referrals' => $referrals->count(),
                'qualified_count' => $qualifiedCount,
                'in_progress_count' => $inProgressCount,
                'credits_earned' => round($creditsEarned, 2),
            ],
            'referrals' => $referrals->map(fn (SalonReferral $referral): array => $this->formatReferralRow($referral))->values()->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function formatReferralRow(SalonReferral $referral): array
    {
        $referredSaloon = $referral->referredSaloon;
        $subscription = $referredSaloon?->activeSubscription;
        $plan = $subscription?->plan;
        $monthsCompleted = $this->countCompletedMonths($referral);
        $qualifyingMonths = $this->qualifyingMonths();
        $progressPercent = min(100, (int) round(($monthsCompleted / $qualifyingMonths) * 100));
        $baseAmount = $this->subscriptionBaseAmount($subscription, $plan);
        $potentialCredit = round($baseAmount * ($this->commissionRate() / 100), 2);
        $credit = $referral->credit;
        $isQualified = $referral->status === SalonReferral::STATUS_QUALIFIED;

        return [
            'id' => $referral->id,
            'salon_name' => $referredSaloon?->name ?? 'Salon',
            'owner_name' => $referral->owner?->name,
            'plan_name' => $plan?->name,
            'status' => $isQualified ? SalonReferral::STATUS_QUALIFIED : SalonReferral::STATUS_IN_PROGRESS,
            'started_at' => $referral->referred_at?->toDateString(),
            'credit_amount' => $isQualified ? (float) ($credit?->credit_amount ?? 0) : $potentialCredit,
            'credit_label' => $isQualified ? 'Credited' : 'Potential',
            'progress_percent' => $isQualified ? 100 : $progressPercent,
            'months_completed' => $isQualified ? $qualifyingMonths : $monthsCompleted,
            'qualifying_months' => $qualifyingMonths,
        ];
    }

    private function shouldQualify(SalonReferral $referral): bool
    {
        if ($referral->credit !== null) {
            return false;
        }

        $subscription = $referral->referredSaloon?->activeSubscription;

        if ($subscription === null || ! $subscription->isCurrentlyActive()) {
            return false;
        }

        return $this->countCompletedMonths($referral) >= $this->qualifyingMonths();
    }

    private function issueCredit(SalonReferral $referral): ?SalonReferralCredit
    {
        if ($referral->credit !== null) {
            return $referral->credit;
        }

        $subscription = $referral->referredSaloon?->activeSubscription;
        $plan = $subscription?->plan;
        $baseAmount = $this->subscriptionBaseAmount($subscription, $plan);
        $rate = $this->commissionRate();
        $creditAmount = round($baseAmount * ($rate / 100), 2);

        return DB::transaction(function () use ($referral, $subscription, $baseAmount, $rate, $creditAmount): SalonReferralCredit {
            $credit = SalonReferralCredit::query()->create([
                'referrer_saloon_id' => $referral->referrer_saloon_id,
                'salon_referral_id' => $referral->id,
                'referred_saloon_id' => $referral->referred_saloon_id,
                'saloon_subscription_id' => $subscription?->id,
                'status' => SalonReferralCredit::STATUS_CREDITED,
                'currency' => 'INR',
                'base_amount' => $baseAmount,
                'commission_rate' => $rate,
                'credit_amount' => $creditAmount,
                'credited_at' => now(),
                'notes' => 'Referral credit issued after qualifying subscription period.',
            ]);

            $referral->update([
                'status' => SalonReferral::STATUS_QUALIFIED,
                'qualified_at' => now(),
            ]);

            return $credit;
        });
    }

    private function countCompletedMonths(SalonReferral $referral): int
    {
        $subscription = $referral->referredSaloon?->activeSubscription;

        if ($subscription === null || ! $subscription->isCurrentlyActive()) {
            return 0;
        }

        $start = $referral->referred_at ?? $referral->created_at;

        if ($start === null) {
            return 0;
        }

        return min($this->qualifyingMonths(), max(0, (int) $start->diffInMonths(now())));
    }

    private function subscriptionBaseAmount(?SaloonSubscription $subscription, ?SubscriptionPlan $plan): float
    {
        if ($plan !== null) {
            return max(0, (float) $plan->price);
        }

        if ($subscription?->saloon !== null && $subscription->saloon->payment_amount !== null) {
            return max(0, (float) $subscription->saloon->payment_amount);
        }

        return 0;
    }
}
