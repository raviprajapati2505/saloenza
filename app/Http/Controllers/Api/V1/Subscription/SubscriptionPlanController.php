<?php

namespace App\Http\Controllers\Api\V1\Subscription;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Subscription\StoreSubscriptionPlanRequest;
use App\Http\Requests\Api\V1\Subscription\UpdateSubscriptionPlanRequest;
use App\Http\Resources\Api\V1\Common\MessageResponseResource;
use App\Http\Resources\Api\V1\Subscription\SubscriptionPlanResource;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Support\Api\ListQuery;
use App\Support\UserPermissions;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SubscriptionPlanController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $validated = ListQuery::validate($request, [
            'is_active' => ['sometimes', 'boolean'],
            'is_public' => ['sometimes', 'boolean'],
        ]);

        $query = SubscriptionPlan::query()->orderBy('sort_order')->orderBy('id');

        if (! $user?->grantsAllPermissions() && ! UserPermissions::allows($user, 'platform.subscription_plans.view')) {
            $query->where('is_public', true)->where('is_active', true);
        } else {
            if (array_key_exists('is_active', $validated)) {
                $query->where('is_active', (bool) $validated['is_active']);
            }
            if (array_key_exists('is_public', $validated)) {
                $query->where('is_public', (bool) $validated['is_public']);
            }
        }

        ListQuery::applySearch($query, $validated['search'] ?? null, ['name', 'slug']);

        $paginator = ListQuery::paginate($query, $validated);

        return response()->json(ListQuery::responsePayload(
            'Subscription plans fetched successfully.',
            'subscription_plans',
            $paginator,
            SubscriptionPlanResource::class,
        ));
    }

    public function store(StoreSubscriptionPlanRequest $request): JsonResponse
    {
        $plan = SubscriptionPlan::query()->create([
            'name' => (string) $request->validated('name'),
            'slug' => (string) $request->validated('slug'),
            'description' => $request->validated('description'),
            'price' => $request->validated('price'),
            'billing_interval' => (string) $request->validated('billing_interval'),
            'trial_days' => (int) $request->validated('trial_days'),
            'max_branches' => $request->validated('max_branches'),
            'max_staff' => $request->validated('max_staff'),
            'modules' => array_values(array_unique($request->validated('modules'))),
            'is_active' => (bool) $request->validated('is_active'),
            'is_public' => (bool) $request->validated('is_public'),
            'sort_order' => (int) ($request->validated('sort_order') ?? 0),
        ]);

        return response()->json([
            'message' => 'Subscription plan created successfully.',
            'data' => [
                'subscription_plan' => (new SubscriptionPlanResource($plan))->resolve(),
            ],
        ], 201);
    }

    public function show(Request $request, SubscriptionPlan $subscriptionPlan): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        if (! $user?->grantsAllPermissions() && ! UserPermissions::allows($user, 'platform.subscription_plans.view')) {
            if (! $subscriptionPlan->is_public || ! $subscriptionPlan->is_active) {
                abort(404);
            }
        }

        return response()->json([
            'message' => 'Subscription plan fetched successfully.',
            'data' => [
                'subscription_plan' => (new SubscriptionPlanResource($subscriptionPlan))->resolve(),
            ],
        ]);
    }

    public function update(UpdateSubscriptionPlanRequest $request, SubscriptionPlan $subscriptionPlan): JsonResponse
    {
        $subscriptionPlan->update([
            'name' => (string) $request->validated('name'),
            'slug' => (string) $request->validated('slug'),
            'description' => $request->validated('description'),
            'price' => $request->validated('price'),
            'billing_interval' => (string) $request->validated('billing_interval'),
            'trial_days' => (int) $request->validated('trial_days'),
            'max_branches' => $request->validated('max_branches'),
            'max_staff' => $request->validated('max_staff'),
            'modules' => array_values(array_unique($request->validated('modules'))),
            'is_active' => (bool) $request->validated('is_active'),
            'is_public' => (bool) $request->validated('is_public'),
            'sort_order' => (int) ($request->validated('sort_order') ?? $subscriptionPlan->sort_order),
        ]);

        return response()->json([
            'message' => 'Subscription plan updated successfully.',
            'data' => [
                'subscription_plan' => (new SubscriptionPlanResource($subscriptionPlan->fresh()))->resolve(),
            ],
        ]);
    }

    public function destroy(SubscriptionPlan $subscriptionPlan): JsonResponse
    {
        if ($subscriptionPlan->subscriptions()->exists()) {
            return response()->json([
                'message' => 'Cannot delete a plan that is assigned to salons.',
            ], 422);
        }

        $subscriptionPlan->delete();

        return (new MessageResponseResource([
            'message' => 'Subscription plan deleted successfully.',
        ]))->response();
    }
}
