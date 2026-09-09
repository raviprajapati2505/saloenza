<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\Subscription\SaloonSubscriptionHistoryResource;
use App\Models\SaloonSubscriptionHistory;
use App\Support\Api\ListQuery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminSubscriptionHistoryController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $validated = ListQuery::validate($request, [
            'saloon_id' => ['sometimes', 'integer'],
            'action' => ['sometimes', 'string'],
            'status' => ['sometimes', 'string'],
            'subscription_plan_id' => ['sometimes', 'integer'],
        ]);

        $query = SaloonSubscriptionHistory::query()
            ->with(['saloon', 'plan', 'fromPlan', 'changedBy'])
            ->latest('id');

        if (! empty($validated['saloon_id'])) {
            $query->where('saloon_id', (int) $validated['saloon_id']);
        }

        if (! empty($validated['action'])) {
            $query->where('action', (string) $validated['action']);
        }

        if (! empty($validated['status'])) {
            $query->where('status', (string) $validated['status']);
        }

        if (! empty($validated['subscription_plan_id'])) {
            $query->where('subscription_plan_id', (int) $validated['subscription_plan_id']);
        }

        if (! empty($validated['search'])) {
            $search = trim((string) $validated['search']);
            $query->where(function ($builder) use ($search): void {
                $builder
                    ->where('notes', 'like', "%{$search}%")
                    ->orWhere('action', 'like', "%{$search}%")
                    ->orWhereHas('saloon', fn ($q) => $q->where('name', 'like', "%{$search}%"))
                    ->orWhereHas('plan', fn ($q) => $q->where('name', 'like', "%{$search}%"));
            });
        }

        $paginator = ListQuery::paginate($query, $validated);

        return response()->json(ListQuery::responsePayload(
            'Subscription history fetched successfully.',
            'subscription_histories',
            $paginator,
            SaloonSubscriptionHistoryResource::class,
        ));
    }
}
