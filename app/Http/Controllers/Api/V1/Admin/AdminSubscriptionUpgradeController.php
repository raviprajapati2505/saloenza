<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\ApproveSubscriptionUpgradeRequest;
use App\Http\Requests\Api\V1\Admin\AssignSalonSubscriptionRequest;
use App\Http\Resources\Api\V1\Subscription\SubscriptionUpgradeOrderResource;
use App\Models\Saloon;
use App\Models\SubscriptionPlan;
use App\Models\SubscriptionUpgradeOrder;
use App\Models\User;
use App\Services\Subscription\SubscriptionUpgradeService;
use App\Support\Api\ListQuery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminSubscriptionUpgradeController extends Controller
{
    public function __construct(
        private readonly SubscriptionUpgradeService $upgradeService,
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $validated = ListQuery::validate($request, [
            'status' => ['sometimes', 'string'],
            'saloon_id' => ['sometimes', 'integer'],
        ]);

        $query = SubscriptionUpgradeOrder::query()
            ->with(['saloon', 'fromPlan', 'toPlan', 'requestedBy', 'approvedBy'])
            ->latest('id');

        if (! empty($validated['status'])) {
            $query->where('status', (string) $validated['status']);
        }

        if (! empty($validated['saloon_id'])) {
            $query->where('saloon_id', (int) $validated['saloon_id']);
        }

        ListQuery::applySearch($query, $validated['search'] ?? null, ['transaction_id', 'notes']);

        $paginator = ListQuery::paginate($query, $validated);

        return response()->json(ListQuery::responsePayload(
            'Subscription upgrade orders fetched successfully.',
            'subscription_upgrade_orders',
            $paginator,
            SubscriptionUpgradeOrderResource::class,
        ));
    }

    public function approve(ApproveSubscriptionUpgradeRequest $request, SubscriptionUpgradeOrder $subscriptionUpgradeOrder): JsonResponse
    {
        /** @var User $admin */
        $admin = $request->user();

        $order = $this->upgradeService->approveManualOrder(
            $admin,
            $subscriptionUpgradeOrder,
            $request->validated(),
        );

        return response()->json([
            'message' => 'Upgrade request approved and subscription updated.',
            'data' => [
                'subscription_upgrade_order' => (new SubscriptionUpgradeOrderResource(
                    $order->load(['saloon', 'fromPlan', 'toPlan', 'requestedBy', 'approvedBy']),
                ))->resolve(),
            ],
        ]);
    }

    public function reject(Request $request, SubscriptionUpgradeOrder $subscriptionUpgradeOrder): JsonResponse
    {
        /** @var User $admin */
        $admin = $request->user();

        $order = $this->upgradeService->rejectManualOrder(
            $admin,
            $subscriptionUpgradeOrder,
            $request->input('reason'),
        );

        return response()->json([
            'message' => 'Upgrade request rejected.',
            'data' => [
                'subscription_upgrade_order' => (new SubscriptionUpgradeOrderResource(
                    $order->load(['saloon', 'fromPlan', 'toPlan', 'requestedBy', 'approvedBy']),
                ))->resolve(),
            ],
        ]);
    }

    public function assign(AssignSalonSubscriptionRequest $request, Saloon $saloon): JsonResponse
    {
        /** @var User $admin */
        $admin = $request->user();
        $plan = SubscriptionPlan::query()->findOrFail((int) $request->validated('subscription_plan_id'));

        $order = $this->upgradeService->assignPlanManually(
            $admin,
            $saloon,
            $plan,
            [
                'payment_type' => $request->validated('payment_type'),
                'amount' => $request->validated('amount'),
                'transaction_id' => $request->validated('transaction_id'),
                'notes' => $request->validated('notes'),
            ],
            startTrial: (bool) ($request->validated('start_trial') ?? false),
            trialDays: array_key_exists('trial_days', $request->validated())
                ? ($request->validated('trial_days') !== null ? (int) $request->validated('trial_days') : null)
                : null,
        );

        return response()->json([
            'message' => 'Salon subscription assigned successfully.',
            'data' => [
                'subscription_upgrade_order' => (new SubscriptionUpgradeOrderResource(
                    $order->load(['saloon', 'fromPlan', 'toPlan', 'approvedBy']),
                ))->resolve(),
            ],
        ]);
    }
}
