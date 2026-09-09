<?php

namespace App\Services\Affiliate;

use App\Models\AffiliateCommission;
use App\Models\AffiliatePartner;
use App\Models\AffiliateReferral;
use App\Models\AffiliateWithdrawalRequest;
use App\Models\Saloon;
use App\Models\SaloonSubscription;
use App\Models\SubscriptionUpgradeOrder;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\HttpException;

class AffiliateCommissionService
{
    public function findActiveAffiliateByCode(?string $code): ?AffiliatePartner
    {
        if ($code === null || trim($code) === '') {
            return null;
        }

        return AffiliatePartner::query()
            ->where('code', strtoupper(trim($code)))
            ->where('status', AffiliatePartner::STATUS_ACTIVE)
            ->first();
    }

    public function attachAffiliateToSaloon(
        Saloon $saloon,
        AffiliatePartner $affiliate,
        ?string $referralCode = null,
        string $source = AffiliateReferral::SOURCE_LINK,
        array $metadata = [],
    ): AffiliateReferral {
        return DB::transaction(function () use ($saloon, $affiliate, $referralCode, $source, $metadata): AffiliateReferral {
            $saloon->update([
                'affiliate_partner_id' => $affiliate->id,
                'affiliate_referral_code' => $referralCode ?: $affiliate->code,
                'affiliate_attributed_at' => $saloon->affiliate_attributed_at ?? now(),
            ]);

            return AffiliateReferral::query()->updateOrCreate(
                ['saloon_id' => $saloon->id],
                [
                    'affiliate_partner_id' => $affiliate->id,
                    'owner_user_id' => $saloon->users()->orderBy('id')->value('id'),
                    'referral_code' => $referralCode ?: $affiliate->code,
                    'source' => $source,
                    'metadata' => $metadata,
                    'referred_at' => now(),
                    'converted_at' => now(),
                ],
            );
        });
    }

    public function recordOnboardingCommission(
        Saloon $saloon,
        float $baseAmount = 0,
        ?string $currency = 'INR',
        ?string $notes = null,
    ): ?AffiliateCommission {
        if ($saloon->affiliate_partner_id === null) {
            return null;
        }

        $saloon->loadMissing(['affiliatePartner', 'affiliateReferral']);
        $affiliate = $saloon->affiliatePartner;

        if ($affiliate === null) {
            return null;
        }

        return $this->createCommission(
            affiliate: $affiliate,
            saloon: $saloon,
            referral: $saloon->affiliateReferral,
            type: AffiliateCommission::TYPE_ONBOARDING,
            rate: (float) $affiliate->onboarding_commission_rate,
            baseAmount: $baseAmount,
            currency: $currency,
            notes: $notes ?? 'Commission for new salon onboarding.',
        );
    }

    public function recordRenewalCommission(
        SubscriptionUpgradeOrder $order,
        ?SaloonSubscription $subscription = null,
    ): ?AffiliateCommission {
        $order->loadMissing('saloon.affiliatePartner', 'saloon.affiliateReferral');

        $saloon = $order->saloon;
        $affiliate = $saloon?->affiliatePartner;

        if ($saloon === null || $affiliate === null) {
            return null;
        }

        return $this->createCommission(
            affiliate: $affiliate,
            saloon: $saloon,
            referral: $saloon->affiliateReferral,
            type: AffiliateCommission::TYPE_RENEWAL,
            rate: (float) $affiliate->renewal_commission_rate,
            baseAmount: (float) $order->amount,
            currency: $order->currency ?: 'INR',
            notes: 'Commission for paid subscription renewal or upgrade.',
            upgradeOrder: $order,
            subscription: $subscription,
        );
    }

    public function releaseMaturedCommissions(AffiliatePartner $affiliate): void
    {
        AffiliateCommission::query()
            ->where('affiliate_partner_id', $affiliate->id)
            ->where('status', AffiliateCommission::STATUS_LOCKED)
            ->whereNotNull('locked_until')
            ->where('locked_until', '<=', now())
            ->update([
                'status' => AffiliateCommission::STATUS_AVAILABLE,
                'available_at' => now(),
                'released_at' => now(),
            ]);
    }

    public function availableCommissionTotal(AffiliatePartner $affiliate): float
    {
        return round((float) AffiliateCommission::query()
            ->where('affiliate_partner_id', $affiliate->id)
            ->where('status', AffiliateCommission::STATUS_AVAILABLE)
            ->sum('commission_amount'), 2);
    }

    /**
     * Lock available commissions against a withdrawal. Splits the last commission
     * when the requested amount is only a portion of that row.
     */
    public function allocateCommissionsForWithdrawal(
        AffiliatePartner $affiliate,
        AffiliateWithdrawalRequest $withdrawal,
        float $amount,
    ): void {
        $amount = round($amount, 2);

        if ($amount <= 0) {
            throw new HttpException(422, 'Withdrawal amount must be greater than zero.');
        }

        $commissions = AffiliateCommission::query()
            ->where('affiliate_partner_id', $affiliate->id)
            ->where('status', AffiliateCommission::STATUS_AVAILABLE)
            ->orderBy('id')
            ->lockForUpdate()
            ->get();

        $availableAmount = round((float) $commissions->sum('commission_amount'), 2);

        if ($amount > $availableAmount) {
            throw new HttpException(422, 'Requested amount exceeds available commission.');
        }

        $remaining = $amount;

        foreach ($commissions as $commission) {
            if ($remaining <= 0) {
                break;
            }

            $commissionAmount = round((float) $commission->commission_amount, 2);

            if ($commissionAmount <= 0) {
                continue;
            }

            if ($commissionAmount <= $remaining) {
                $commission->update([
                    'status' => AffiliateCommission::STATUS_REQUESTED,
                    'affiliate_withdrawal_request_id' => $withdrawal->id,
                ]);
                $remaining = round($remaining - $commissionAmount, 2);

                continue;
            }

            $this->splitCommissionForPartialWithdrawal($commission, $withdrawal, $remaining);
            $remaining = 0;
        }

        if ($remaining > 0) {
            throw new HttpException(422, 'Unable to allocate commissions for the requested withdrawal amount.');
        }
    }

    private function splitCommissionForPartialWithdrawal(
        AffiliateCommission $commission,
        AffiliateWithdrawalRequest $withdrawal,
        float $takeAmount,
    ): void {
        $takeAmount = round($takeAmount, 2);
        $originalAmount = round((float) $commission->commission_amount, 2);
        $remainderAmount = round($originalAmount - $takeAmount, 2);

        if ($takeAmount <= 0 || $remainderAmount <= 0) {
            throw new HttpException(422, 'Unable to split commission for partial withdrawal.');
        }

        $originalBase = round((float) $commission->base_amount, 2);
        $takeBase = $originalAmount > 0
            ? round($originalBase * ($takeAmount / $originalAmount), 2)
            : 0.0;
        $remainderBase = round($originalBase - $takeBase, 2);

        $meta = is_array($commission->meta) ? $commission->meta : [];
        $meta['split_from_commission_id'] = $commission->id;
        $meta['split_for_withdrawal_id'] = $withdrawal->id;

        AffiliateCommission::query()->create([
            'affiliate_partner_id' => $commission->affiliate_partner_id,
            'affiliate_referral_id' => $commission->affiliate_referral_id,
            'saloon_id' => $commission->saloon_id,
            'subscription_upgrade_order_id' => $commission->subscription_upgrade_order_id,
            'saloon_subscription_id' => $commission->saloon_subscription_id,
            'type' => $commission->type,
            'status' => AffiliateCommission::STATUS_AVAILABLE,
            'currency' => $commission->currency,
            'base_amount' => $remainderBase,
            'commission_rate' => $commission->commission_rate,
            'commission_amount' => $remainderAmount,
            'locked_until' => $commission->locked_until,
            'available_at' => $commission->available_at ?? now(),
            'released_at' => $commission->released_at ?? now(),
            'notes' => $commission->notes,
            'meta' => $meta,
        ]);

        $commission->update([
            'base_amount' => $takeBase,
            'commission_amount' => $takeAmount,
            'status' => AffiliateCommission::STATUS_REQUESTED,
            'affiliate_withdrawal_request_id' => $withdrawal->id,
            'meta' => array_merge($meta, [
                'split_remainder_amount' => $remainderAmount,
            ]),
        ]);
    }

    private function createCommission(
        AffiliatePartner $affiliate,
        Saloon $saloon,
        ?AffiliateReferral $referral,
        string $type,
        float $rate,
        float $baseAmount,
        ?string $currency = 'INR',
        ?string $notes = null,
        ?SubscriptionUpgradeOrder $upgradeOrder = null,
        ?SaloonSubscription $subscription = null,
    ): AffiliateCommission {
        $existing = AffiliateCommission::query()
            ->when($upgradeOrder !== null, fn ($query) => $query->where('subscription_upgrade_order_id', $upgradeOrder->id))
            ->when($upgradeOrder === null, fn ($query) => $query->whereNull('subscription_upgrade_order_id'))
            ->where('saloon_id', $saloon->id)
            ->where('type', $type)
            ->latest('id')
            ->first();

        if ($existing !== null) {
            return $existing;
        }

        $commissionAmount = round(max(0, $baseAmount) * ($rate / 100), 2);
        $lockedUntil = now()->addDays(max(0, (int) $affiliate->commission_lock_days));

        return AffiliateCommission::query()->create([
            'affiliate_partner_id' => $affiliate->id,
            'affiliate_referral_id' => $referral?->id,
            'saloon_id' => $saloon->id,
            'subscription_upgrade_order_id' => $upgradeOrder?->id,
            'saloon_subscription_id' => $subscription?->id,
            'type' => $type,
            'status' => AffiliateCommission::STATUS_LOCKED,
            'currency' => $currency ?: 'INR',
            'base_amount' => $baseAmount,
            'commission_rate' => $rate,
            'commission_amount' => $commissionAmount,
            'locked_until' => $lockedUntil,
            'notes' => $notes,
        ]);
    }
}
