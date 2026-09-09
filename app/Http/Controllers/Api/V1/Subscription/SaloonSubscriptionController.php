<?php

namespace App\Http\Controllers\Api\V1\Subscription;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Subscription\CheckoutSubscriptionRequest;
use App\Http\Requests\Api\V1\Subscription\ConfirmSubscriptionCheckoutRequest;
use App\Http\Requests\Api\V1\Subscription\UpgradeSubscriptionRequest;
use App\Http\Resources\Api\V1\Subscription\SaloonSubscriptionResource;
use App\Http\Resources\Api\V1\Subscription\SubscriptionPlanResource;
use App\Http\Resources\Api\V1\Subscription\SubscriptionUpgradeOrderResource;
use App\Models\SubscriptionPlan;
use App\Models\SubscriptionUpgradeOrder;
use App\Models\User;
use App\Services\Subscription\SubscriptionUpgradeService;
use App\Support\Payment\PaymentConfig;
use App\Support\Subscription\SubscriptionEntitlements;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SaloonSubscriptionController extends Controller
{
    public function __construct(
        private readonly SubscriptionEntitlements $entitlements,
        private readonly SubscriptionUpgradeService $upgradeService,
    ) {
    }

    public function show(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        if ($user->saloon_id === null) {
            throw new AuthorizationException('Your account is not linked to a saloon.');
        }

        $user->loadMissing('saloon');
        $snapshot = $this->entitlements->snapshot($user->saloon);
        $summary = $this->entitlements->salonSubscriptionSummary($user->saloon);
        $pendingOrder = $this->upgradeService->pendingOrderForSaloon($user->saloon);

        return response()->json([
            'message' => 'Subscription fetched successfully.',
            'data' => [
                'subscription' => $snapshot['subscription']
                    ? (new SaloonSubscriptionResource($snapshot['subscription']->loadMissing('plan')))->resolve()
                    : ($summary['subscription'] ?? null),
                'plan' => $snapshot['plan']
                    ? (new SubscriptionPlanResource($snapshot['plan']))->resolve()
                    : ($summary['plan'] ?? null),
                'modules' => $snapshot['modules'],
                'limits' => $snapshot['limits'],
                'trial_ends_at' => $snapshot['trial_ends_at'],
                'access_mode' => $summary['access_mode'],
                'subscription_summary' => [
                    'lifecycle' => $summary['lifecycle'],
                    'lifecycle_label' => $summary['lifecycle_label'],
                    'days_remaining' => $summary['days_remaining'],
                    'renewal_due_at' => $summary['renewal_due_at'],
                ],
                'pending_upgrade_order' => $pendingOrder
                    ? (new SubscriptionUpgradeOrderResource($pendingOrder))->resolve()
                    : null,
                'plan_options' => $this->upgradeService->planSwitchOptionsForSaloon($user->saloon),
                'billing' => PaymentConfig::publicConfig(),
            ],
        ]);
    }

    public function upgrade(UpgradeSubscriptionRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        if (! PaymentConfig::allowInstantUpgrade()) {
            return response()->json([
                'message' => 'Instant upgrades are disabled. Start checkout from billing or contact the platform admin.',
            ], 422);
        }

        $plan = $this->entitlements->findPublicPlanById((int) $request->validated('subscription_plan_id'));

        if ($plan === null) {
            return response()->json([
                'message' => 'The selected subscription plan is not available.',
            ], 422);
        }

        $result = $this->upgradeService->fulfillInstantUpgrade($user, $plan);
        $snapshot = $result['subscription'];

        return response()->json([
            'message' => 'Subscription plan updated successfully.',
            'data' => [
                'upgrade_order' => (new SubscriptionUpgradeOrderResource($result['order']))->resolve(),
                'subscription' => $snapshot['subscription']
                    ? (new SaloonSubscriptionResource($snapshot['subscription']->loadMissing('plan')))->resolve()
                    : null,
                'plan' => $snapshot['plan']
                    ? (new SubscriptionPlanResource($snapshot['plan']))->resolve()
                    : null,
                'modules' => $snapshot['modules'],
                'limits' => $snapshot['limits'],
                'trial_ends_at' => $snapshot['trial_ends_at'],
            ],
        ]);
    }

    public function checkout(CheckoutSubscriptionRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $plan = SubscriptionPlan::query()->findOrFail((int) $request->validated('subscription_plan_id'));

        $result = $this->upgradeService->initiateCheckout(
            $user,
            $plan,
            $request->validated('notes'),
        );

        return response()->json([
            'message' => $result['checkout']['message'] ?? 'Checkout initiated successfully.',
            'data' => [
                'upgrade_order' => (new SubscriptionUpgradeOrderResource($result['order']))->resolve(),
                'checkout' => $result['checkout'],
                'billing' => PaymentConfig::publicConfig(),
            ],
        ], 201);
    }

    public function confirmCheckout(ConfirmSubscriptionCheckoutRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $order = SubscriptionUpgradeOrder::query()->findOrFail((int) $request->validated('upgrade_order_id'));

        $payload = array_filter([
            'razorpay_payment_id' => $request->validated('razorpay_payment_id'),
            'razorpay_order_id' => $request->validated('razorpay_order_id'),
            'razorpay_signature' => $request->validated('razorpay_signature'),
            'stripe_session_id' => $request->validated('stripe_session_id'),
        ], fn ($value) => $value !== null && $value !== '');

        $order = $this->upgradeService->confirmGatewayPayment($user, $order, $payload);

        $user->loadMissing('saloon');
        $snapshot = $this->entitlements->snapshot($user->saloon);

        return response()->json([
            'message' => 'Payment confirmed and subscription upgraded successfully.',
            'data' => [
                'upgrade_order' => (new SubscriptionUpgradeOrderResource($order->load(['toPlan', 'fromPlan'])))->resolve(),
                'subscription' => $snapshot['subscription']
                    ? (new SaloonSubscriptionResource($snapshot['subscription']->loadMissing('plan')))->resolve()
                    : null,
                'plan' => $snapshot['plan']
                    ? (new SubscriptionPlanResource($snapshot['plan']))->resolve()
                    : null,
                'modules' => $snapshot['modules'],
                'limits' => $snapshot['limits'],
                'trial_ends_at' => $snapshot['trial_ends_at'],
            ],
        ]);
    }
}
