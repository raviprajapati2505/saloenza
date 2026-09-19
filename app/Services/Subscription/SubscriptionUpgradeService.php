<?php

namespace App\Services\Subscription;

use App\Models\Saloon;
use App\Models\SubscriptionPlan;
use App\Models\SubscriptionUpgradeOrder;
use App\Models\User;
use App\Http\Resources\Api\V1\Subscription\SubscriptionPlanResource;
use App\Services\Affiliate\AffiliateCommissionService;
use App\Services\Notifications\PlatformAdminNotifier;
use App\Services\Payment\PaymentGatewayManager;
use App\Support\Payment\PaymentConfig;
use App\Support\Subscription\SubscriptionEntitlements;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Symfony\Component\HttpKernel\Exception\HttpException;

class SubscriptionUpgradeService
{
    public function __construct(
        private readonly SubscriptionEntitlements $entitlements,
        private readonly PaymentGatewayManager $gatewayManager,
        private readonly AffiliateCommissionService $affiliateCommissionService,
        private readonly PlatformAdminNotifier $platformAdminNotifier,
    ) {
    }

    public function calculateUpgradeAmount(?SubscriptionPlan $currentPlan, SubscriptionPlan $targetPlan): float
    {
        $targetPrice = (float) $targetPlan->price;
        $currentPrice = (float) ($currentPlan?->price ?? 0);

        if ($targetPrice <= 0) {
            return 0.0;
        }

        return max(0, round($targetPrice - $currentPrice, 2));
    }

    /**
     * @return array{order: SubscriptionUpgradeOrder, checkout: array<string, mixed>}
     */
    public function initiateCheckout(User $user, SubscriptionPlan $targetPlan, ?string $notes = null): array
    {
        $user->loadMissing('saloon');
        $saloon = $user->saloon;

        if ($saloon === null) {
            throw new HttpException(403, 'Your account is not linked to a saloon.');
        }

        $this->assertPlanChangeAllowed($saloon, $targetPlan);

        $existingPending = SubscriptionUpgradeOrder::query()
            ->where('saloon_id', $saloon->id)
            ->whereIn('status', [
                SubscriptionUpgradeOrder::STATUS_PENDING,
                SubscriptionUpgradeOrder::STATUS_AWAITING_PAYMENT,
            ])
            ->exists();

        if ($existingPending) {
            throw new HttpException(422, 'An upgrade request is already pending for this salon.');
        }

        $currentPlan = $this->entitlements->activePlan($saloon);
        $amount = $this->calculateUpgradeAmount($currentPlan, $targetPlan);

        // Zero-cost plan changes (e.g. free) apply immediately.
        if ($amount <= 0 && $targetPlan->isFreePlan()) {
            return $this->fulfillInstantly(
                $user,
                $saloon,
                $targetPlan,
                $currentPlan,
                $amount,
                SubscriptionUpgradeOrder::CHANNEL_MANUAL,
            );
        }

        $forceManual = PaymentConfig::offlineOnly() || ! PaymentConfig::isGatewayConfigured();
        $gateway = $forceManual
            ? $this->gatewayManager->driver(SubscriptionUpgradeOrder::CHANNEL_MANUAL)
            : $this->gatewayManager->activeCheckoutDriver();
        $channel = $gateway->driver();

        if ($channel === SubscriptionUpgradeOrder::CHANNEL_MANUAL && ! PaymentConfig::manualAllowTenantRequests()) {
            throw new HttpException(422, 'Upgrade requests are disabled. Contact the platform admin to upgrade your plan.');
        }

        if (in_array($channel, [SubscriptionUpgradeOrder::CHANNEL_RAZORPAY, SubscriptionUpgradeOrder::CHANNEL_STRIPE], true) && $amount <= 0) {
            return $this->fulfillInstantly($user, $saloon, $targetPlan, $currentPlan, $amount, $channel);
        }

        $order = SubscriptionUpgradeOrder::query()->create([
            'saloon_id' => $saloon->id,
            'requested_by_user_id' => $user->id,
            'from_subscription_plan_id' => $currentPlan?->id,
            'to_subscription_plan_id' => $targetPlan->id,
            'amount' => $amount,
            'currency' => PaymentConfig::currency(),
            'channel' => $channel,
            'status' => $channel === SubscriptionUpgradeOrder::CHANNEL_MANUAL
                ? SubscriptionUpgradeOrder::STATUS_PENDING
                : SubscriptionUpgradeOrder::STATUS_AWAITING_PAYMENT,
            'notes' => $notes,
        ]);

        $checkout = $gateway->createCheckout($order);

        if ($channel === SubscriptionUpgradeOrder::CHANNEL_RAZORPAY) {
            $order->update([
                'gateway_order_id' => (string) ($checkout['razorpay_order_id'] ?? ''),
                'gateway_payload' => $checkout,
            ]);
        }

        if ($channel === SubscriptionUpgradeOrder::CHANNEL_STRIPE) {
            $order->update([
                'gateway_order_id' => (string) ($checkout['stripe_session_id'] ?? ''),
                'gateway_payload' => $checkout,
            ]);
        }

        if ($order->status === SubscriptionUpgradeOrder::STATUS_PENDING) {
            $salonName = $saloon->name ?: 'A salon';
            $planName = $targetPlan->name ?: 'a paid plan';

            $this->platformAdminNotifier->notify(
                event: 'subscription.upgrade.requested',
                title: 'Subscription upgrade requested',
                body: "{$salonName} requested an upgrade to {$planName}.",
                actionUrl: '/admin/subscription-upgrades?status=pending',
                requiredPermissions: ['platform.upgrade_requests.view'],
                meta: [
                    'subscription_upgrade_order_id' => $order->id,
                    'saloon_id' => $saloon->id,
                ],
            );
        }

        return [
            'order' => $order->fresh(['toPlan', 'fromPlan']),
            'checkout' => $checkout,
        ];
    }

    /**
     * Paid plan selected during self-serve onboarding.
     *
     * Plans with a trial start that paid plan immediately (so modules like inventory
     * match the selected plan) while a pending offline payment order is still created.
     * Plans without a trial stay on Free and remain locked until admin activation.
     */
    public function requestPaidPlanActivation(User $user, Saloon $saloon, SubscriptionPlan $targetPlan): SubscriptionUpgradeOrder
    {
        if (! $targetPlan->isPaidPlan()) {
            throw new HttpException(422, 'Only paid plans require offline activation.');
        }

        $this->assertPlanChangeAllowed($saloon, $targetPlan);

        $startsWithTrial = (int) $targetPlan->trial_days > 0;
        $previousPlan = $this->entitlements->activePlan($saloon);

        if ($startsWithTrial) {
            $this->entitlements->assignPlan($saloon, $targetPlan, startTrial: true);
            $saloon->markActivated();
        } else {
            $freePlan = SubscriptionPlan::query()->where('slug', 'free')->first()
                ?? SubscriptionPlan::query()->where('slug', 'free-trial')->first();

            $active = $this->entitlements->activeSubscription($saloon);
            $activePlan = $this->entitlements->activePlan($saloon);

            if ($freePlan !== null && ($active === null || $activePlan?->isPaidPlan())) {
                $this->entitlements->assignPlan($saloon, $freePlan, startTrial: true);
            }
        }

        $existing = $this->pendingOrderForSaloon($saloon);
        if ($existing !== null) {
            if ((int) $existing->to_subscription_plan_id === (int) $targetPlan->id) {
                if ($startsWithTrial) {
                    $this->entitlements->assignPlan($saloon, $targetPlan, startTrial: true);
                    $saloon->markActivated();
                } else {
                    $saloon->markActivationPending();
                }

                return $existing;
            }

            $existing->update([
                'status' => SubscriptionUpgradeOrder::STATUS_REJECTED,
                'notes' => trim(($existing->notes ? $existing->notes.' ' : '').'Superseded by a newer plan request during onboarding.'),
            ]);
        }

        $amount = $this->calculateUpgradeAmount($previousPlan, $targetPlan);

        $order = SubscriptionUpgradeOrder::query()->create([
            'saloon_id' => $saloon->id,
            'requested_by_user_id' => $user->id,
            'from_subscription_plan_id' => $previousPlan?->id,
            'to_subscription_plan_id' => $targetPlan->id,
            'amount' => $amount > 0 ? $amount : (float) $targetPlan->price,
            'currency' => PaymentConfig::currency(),
            'channel' => SubscriptionUpgradeOrder::CHANNEL_MANUAL,
            'status' => SubscriptionUpgradeOrder::STATUS_PENDING,
            'notes' => $startsWithTrial
                ? 'Paid plan trial started during salon onboarding. Awaiting offline payment confirmation.'
                : 'Paid plan selected during salon onboarding. Awaiting offline payment and platform activation.',
        ]);

        if ($startsWithTrial) {
            $saloon->markActivated();
        } else {
            $saloon->markActivationPending();
        }

        $salonName = $saloon->name ?: 'A salon';
        $planName = $targetPlan->name ?: 'a paid plan';

        $this->platformAdminNotifier->notify(
            event: 'subscription.activation.requested',
            title: 'Salon activation requested',
            body: $startsWithTrial
                ? "{$salonName} started a {$planName} trial. Collect offline payment and approve to convert to a paid subscription."
                : "{$salonName} requested {$planName}. Contact them to collect offline payment, then approve to activate.",
            actionUrl: '/admin/subscription-upgrades?status=pending',
            requiredPermissions: ['platform.upgrade_requests.view'],
            meta: [
                'subscription_upgrade_order_id' => $order->id,
                'saloon_id' => $saloon->id,
            ],
        );

        return $order->fresh(['toPlan', 'fromPlan', 'saloon']);
    }

    /**
     * @param array<string, mixed> $payload
     */
    public function confirmGatewayPayment(User $user, SubscriptionUpgradeOrder $order, array $payload): SubscriptionUpgradeOrder
    {
        if ($order->requested_by_user_id !== null && (int) $order->requested_by_user_id !== (int) $user->id) {
            throw new HttpException(403, 'You cannot confirm this upgrade order.');
        }

        if ($user->saloon_id !== null && (int) $order->saloon_id !== (int) $user->saloon_id && ! $user->grantsAllPermissions()) {
            throw new HttpException(403, 'You cannot confirm this upgrade order.');
        }

        if ($order->status === SubscriptionUpgradeOrder::STATUS_COMPLETED) {
            return $order;
        }

        if (! $order->isAwaitingPayment()) {
            throw new HttpException(422, 'This upgrade order is not awaiting payment.');
        }

        $gateway = $this->gatewayManager->driver($order->channel);

        if (! $gateway->verifyPayment($order, $payload)) {
            $order->update(['status' => SubscriptionUpgradeOrder::STATUS_FAILED]);

            throw new HttpException(422, 'Payment verification failed.');
        }

        return DB::transaction(function () use ($order, $payload, $user): SubscriptionUpgradeOrder {
            $paymentId = (string) (
                $payload['razorpay_payment_id']
                ?? $payload['stripe_payment_intent']
                ?? $payload['stripe_session_id']
                ?? $order->gateway_payment_id
                ?? ''
            );

            $order->update([
                'status' => SubscriptionUpgradeOrder::STATUS_COMPLETED,
                'gateway_payment_id' => $paymentId !== '' ? $paymentId : $order->gateway_payment_id,
                'gateway_payload' => array_merge($order->gateway_payload ?? [], $payload),
                'payment_type' => 'online',
                'transaction_id' => $paymentId !== '' ? $paymentId : $order->transaction_id,
                'paid_at' => now(),
                'approved_by_user_id' => $user->id,
            ]);

            return $this->fulfillOrder($order, $user);
        });
    }

    /**
     * Idempotent fulfillment used by payment provider webhooks.
     *
     * @param array<string, mixed> $payload
     */
    public function fulfillFromWebhook(SubscriptionUpgradeOrder $order, array $payload, string $source = 'webhook'): SubscriptionUpgradeOrder
    {
        if ($order->status === SubscriptionUpgradeOrder::STATUS_COMPLETED) {
            return $order->fresh(['toPlan', 'fromPlan', 'saloon']);
        }

        if (! $order->isAwaitingPayment()) {
            throw new HttpException(422, 'This upgrade order is not awaiting payment.');
        }

        $paymentId = (string) (
            $payload['razorpay_payment_id']
            ?? $payload['stripe_payment_intent']
            ?? $payload['stripe_session_id']
            ?? $order->gateway_payment_id
            ?? ''
        );

        return DB::transaction(function () use ($order, $payload, $paymentId, $source): SubscriptionUpgradeOrder {
            $order->update([
                'status' => SubscriptionUpgradeOrder::STATUS_COMPLETED,
                'gateway_payment_id' => $paymentId !== '' ? $paymentId : $order->gateway_payment_id,
                'gateway_payload' => array_merge($order->gateway_payload ?? [], [
                    'webhook' => $payload,
                    'webhook_source' => $source,
                ]),
                'payment_type' => 'online',
                'transaction_id' => $paymentId !== '' ? $paymentId : $order->transaction_id,
                'paid_at' => now(),
            ]);

            $actor = $order->requestedBy
                ?? User::query()->where('is_system_admin', true)->orderBy('id')->first();

            if ($actor === null) {
                throw new RuntimeException('Unable to resolve actor for webhook fulfillment.');
            }

            return $this->fulfillOrder($order, $actor);
        });
    }

    public function findAwaitingOrderByGatewayReference(string $channel, string $gatewayOrderId): ?SubscriptionUpgradeOrder
    {
        return SubscriptionUpgradeOrder::query()
            ->where('channel', $channel)
            ->where('gateway_order_id', $gatewayOrderId)
            ->where('status', SubscriptionUpgradeOrder::STATUS_AWAITING_PAYMENT)
            ->latest('id')
            ->first();
    }

    public function findAwaitingOrderById(int $orderId): ?SubscriptionUpgradeOrder
    {
        return SubscriptionUpgradeOrder::query()
            ->whereKey($orderId)
            ->where('status', SubscriptionUpgradeOrder::STATUS_AWAITING_PAYMENT)
            ->first();
    }

    public function approveManualOrder(
        User $admin,
        SubscriptionUpgradeOrder $order,
        array $paymentDetails,
    ): SubscriptionUpgradeOrder {
        if (! $order->isPendingManual()) {
            throw new HttpException(422, 'Only pending manual upgrade requests can be approved.');
        }

        return DB::transaction(function () use ($admin, $order, $paymentDetails): SubscriptionUpgradeOrder {
            $order->update([
                'status' => SubscriptionUpgradeOrder::STATUS_COMPLETED,
                'payment_type' => $paymentDetails['payment_type'] ?? $order->payment_type,
                'transaction_id' => $paymentDetails['transaction_id'] ?? $order->transaction_id,
                'amount' => $paymentDetails['amount'] ?? $order->amount,
                'notes' => $paymentDetails['notes'] ?? $order->notes,
                'paid_at' => now(),
                'approved_by_user_id' => $admin->id,
            ]);

            return $this->fulfillOrder($order, $admin);
        });
    }

    public function rejectManualOrder(User $admin, SubscriptionUpgradeOrder $order, ?string $reason = null): SubscriptionUpgradeOrder
    {
        if (! $order->isPendingManual()) {
            throw new HttpException(422, 'Only pending manual upgrade requests can be rejected.');
        }

        $order->update([
            'status' => SubscriptionUpgradeOrder::STATUS_REJECTED,
            'approved_by_user_id' => $admin->id,
            'notes' => trim(($order->notes ? $order->notes.' ' : '').($reason ?? '')),
        ]);

        return $order->fresh();
    }

    /**
     * Platform admin assigns a plan directly (offline payment collected).
     *
     * @param array<string, mixed> $paymentDetails
     */
    public function assignPlanManually(
        User $admin,
        Saloon $saloon,
        SubscriptionPlan $targetPlan,
        array $paymentDetails = [],
        bool $startTrial = false,
        ?int $trialDays = null,
    ): SubscriptionUpgradeOrder {
        if (! $targetPlan->is_active || ! $targetPlan->is_public) {
            throw new HttpException(422, 'The selected subscription plan is not available.');
        }

        $this->entitlements->ensureCanSwitchToPlan($saloon, $targetPlan);

        $currentPlan = $this->entitlements->activePlan($saloon);

        $amount = array_key_exists('amount', $paymentDetails) && $paymentDetails['amount'] !== null
            ? (float) $paymentDetails['amount']
            : $this->calculateUpgradeAmount($currentPlan, $targetPlan);

        return DB::transaction(function () use ($admin, $saloon, $targetPlan, $currentPlan, $paymentDetails, $amount, $startTrial, $trialDays): SubscriptionUpgradeOrder {
            $order = SubscriptionUpgradeOrder::query()->create([
                'saloon_id' => $saloon->id,
                'requested_by_user_id' => null,
                'approved_by_user_id' => $admin->id,
                'from_subscription_plan_id' => $currentPlan?->id,
                'to_subscription_plan_id' => $targetPlan->id,
                'amount' => $amount,
                'currency' => PaymentConfig::currency(),
                'channel' => SubscriptionUpgradeOrder::CHANNEL_MANUAL,
                'status' => SubscriptionUpgradeOrder::STATUS_COMPLETED,
                'payment_type' => $paymentDetails['payment_type'] ?? 'offline',
                'transaction_id' => $paymentDetails['transaction_id'] ?? null,
                'notes' => $paymentDetails['notes'] ?? 'Assigned by platform admin.',
                'paid_at' => now(),
            ]);

            $this->entitlements->assignPlan(
                $saloon,
                $targetPlan,
                startTrial: $startTrial,
                trialDays: $trialDays,
                changedByUserId: $admin->id,
                upgradeOrderId: $order->id,
                notes: $paymentDetails['notes'] ?? 'Assigned by platform admin.',
            );

            $saloon->update([
                'payment_type' => $paymentDetails['payment_type'] ?? $saloon->payment_type,
                'payment_amount' => $amount > 0 ? $amount : $saloon->payment_amount,
                'transaction_id' => $paymentDetails['transaction_id'] ?? $saloon->transaction_id,
            ]);

            $order->update(['fulfilled_at' => now()]);

            $this->affiliateCommissionService->recordRenewalCommission(
                $order->fresh(['saloon.affiliatePartner', 'saloon.affiliateReferral']),
                $saloon->activeSubscription()->first(),
            );

            return $order->fresh(['toPlan', 'fromPlan', 'saloon']);
        });
    }

    public function fulfillInstantUpgrade(User $user, SubscriptionPlan $targetPlan): array
    {
        if (! PaymentConfig::allowInstantUpgrade()) {
            throw new HttpException(422, 'Instant upgrades are disabled. Use checkout or contact the platform admin.');
        }

        $user->loadMissing('saloon');
        $saloon = $user->saloon;

        if ($saloon === null) {
            throw new HttpException(403, 'Your account is not linked to a saloon.');
        }

        $this->assertPlanChangeAllowed($saloon, $targetPlan);

        $currentPlan = $this->entitlements->activePlan($saloon);
        $amount = $this->calculateUpgradeAmount($currentPlan, $targetPlan);

        $result = $this->fulfillInstantly(
            $user,
            $saloon,
            $targetPlan,
            $currentPlan,
            $amount,
            PaymentConfig::driver(),
        );

        return [
            'order' => $result['order'],
            'subscription' => $this->entitlements->snapshot($saloon),
        ];
    }

    public function pendingOrderForSaloon(Saloon $saloon): ?SubscriptionUpgradeOrder
    {
        return SubscriptionUpgradeOrder::query()
            ->with(['toPlan', 'fromPlan'])
            ->where('saloon_id', $saloon->id)
            ->whereIn('status', [
                SubscriptionUpgradeOrder::STATUS_PENDING,
                SubscriptionUpgradeOrder::STATUS_AWAITING_PAYMENT,
            ])
            ->latest('id')
            ->first();
    }

    private function fulfillOrder(SubscriptionUpgradeOrder $order, User $actor): SubscriptionUpgradeOrder
    {
        $order->loadMissing(['saloon', 'toPlan']);

        if ($order->saloon === null || $order->toPlan === null) {
            throw new RuntimeException('Upgrade order is missing salon or target plan.');
        }

        $this->entitlements->ensureCanSwitchToPlan($order->saloon, $order->toPlan);

        $this->entitlements->assignPlan(
            $order->saloon,
            $order->toPlan,
            startTrial: false,
            changedByUserId: $actor->id,
            upgradeOrderId: $order->id,
            notes: 'Fulfilled from subscription upgrade order.',
        );

        $order->saloon->markActivated();

        if ($order->amount > 0 || $order->transaction_id) {
            $order->saloon->update([
                'payment_type' => $order->payment_type ?? $order->saloon->payment_type,
                'payment_amount' => $order->amount > 0 ? $order->amount : $order->saloon->payment_amount,
                'transaction_id' => $order->transaction_id ?? $order->saloon->transaction_id,
            ]);
        }

        $order->update([
            'fulfilled_at' => now(),
            'approved_by_user_id' => $order->approved_by_user_id ?? $actor->id,
        ]);

        $this->affiliateCommissionService->recordRenewalCommission(
            $order,
            $order->saloon->activeSubscription()->first(),
        );

        return $order->fresh(['toPlan', 'fromPlan', 'saloon']);
    }

    /**
     * @return array{order: SubscriptionUpgradeOrder, checkout: array<string, mixed>}
     */
    private function fulfillInstantly(
        User $user,
        Saloon $saloon,
        SubscriptionPlan $targetPlan,
        ?SubscriptionPlan $currentPlan,
        float $amount,
        string $channel,
    ): array {
        return DB::transaction(function () use ($user, $saloon, $targetPlan, $currentPlan, $amount, $channel): array {
            $order = SubscriptionUpgradeOrder::query()->create([
                'saloon_id' => $saloon->id,
                'requested_by_user_id' => $user->id,
                'approved_by_user_id' => $user->id,
                'from_subscription_plan_id' => $currentPlan?->id,
                'to_subscription_plan_id' => $targetPlan->id,
                'amount' => $amount,
                'currency' => PaymentConfig::currency(),
                'channel' => $channel,
                'status' => SubscriptionUpgradeOrder::STATUS_COMPLETED,
                'payment_type' => $amount > 0 ? 'waived' : 'free',
                'paid_at' => now(),
            ]);

            $this->entitlements->assignPlan(
                $saloon,
                $targetPlan,
                startTrial: false,
                changedByUserId: $user->id,
                upgradeOrderId: $order->id,
                notes: 'Instant subscription plan change.',
            );
            $order->update(['fulfilled_at' => now()]);

            $this->affiliateCommissionService->recordRenewalCommission(
                $order->fresh(['saloon.affiliatePartner', 'saloon.affiliateReferral']),
                $saloon->activeSubscription()->first(),
            );

            return [
                'order' => $order->fresh(['toPlan', 'fromPlan']),
                'checkout' => [
                    'mode' => 'instant',
                    'order_id' => $order->id,
                    'message' => 'Subscription plan updated successfully.',
                ],
            ];
        });
    }

    private function assertPlanChangeAllowed(Saloon $saloon, SubscriptionPlan $targetPlan): void
    {
        if (! $targetPlan->is_active || ! $targetPlan->is_public) {
            throw new HttpException(422, 'The selected subscription plan is not available.');
        }

        $this->entitlements->ensureCanSwitchToPlan($saloon, $targetPlan);
    }

    /**
     * @return list<array{plan: array<string, mixed>, eligibility: array<string, mixed>}>
     */
    public function planSwitchOptionsForSaloon(Saloon $saloon): array
    {
        $currentPlan = $this->entitlements->activePlan($saloon);

        return SubscriptionPlan::query()
            ->where('is_active', true)
            ->where('is_public', true)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->map(function (SubscriptionPlan $plan) use ($saloon, $currentPlan): array {
                $evaluation = $this->entitlements->evaluatePlanSwitch($saloon, $plan, $currentPlan);

                return [
                    'plan' => (new SubscriptionPlanResource($plan))->resolve(),
                    'eligibility' => $evaluation,
                ];
            })
            ->values()
            ->all();
    }
}
