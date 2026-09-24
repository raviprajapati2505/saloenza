<?php

namespace App\Http\Controllers\Api\V1\Customer;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Customer\CustomerClvService;
use App\Support\EnsuresPermission;
use App\Support\Tenant\TenantScope;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CustomerClvController extends Controller
{
    public function __construct(
        private readonly CustomerClvService $clvService,
    ) {
    }

    public function summary(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        EnsuresPermission::any($user, ['analytics.view', 'customers.view', 'crm.retention.view']);

        $saloonId = TenantScope::resolveSaloonId(
            $user,
            $request->filled('saloon_id') ? (int) $request->integer('saloon_id') : null,
        );

        $validated = $request->validate([
            'limit' => ['sometimes', 'integer', 'min:1', 'max:50'],
        ]);

        $summary = $this->clvService->summary($saloonId, (int) ($validated['limit'] ?? 10));

        return response()->json([
            'message' => 'CLV summary fetched successfully.',
            'data' => [
                'summary' => $summary,
            ],
        ]);
    }

    public function index(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        EnsuresPermission::any($user, ['analytics.view', 'customers.view', 'crm.retention.view']);

        $saloonId = TenantScope::resolveSaloonId(
            $user,
            $request->filled('saloon_id') ? (int) $request->integer('saloon_id') : null,
        );

        $validated = $request->validate([
            'tier' => ['sometimes', 'nullable', 'string', 'in:platinum,gold,silver,bronze'],
            'limit' => ['sometimes', 'integer', 'min:1', 'max:500'],
        ]);

        $rows = $this->clvService->listForSalon(
            $saloonId,
            $validated['tier'] ?? null,
            (int) ($validated['limit'] ?? 100),
        );

        return response()->json([
            'message' => 'Customer CLV list fetched successfully.',
            'data' => [
                'customers' => $rows,
            ],
        ]);
    }

    public function recompute(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        EnsuresPermission::any($user, ['analytics.view', 'customers.manage', 'crm.retention.manage']);

        $saloonId = TenantScope::resolveSaloonId(
            $user,
            $request->filled('saloon_id') ? (int) $request->integer('saloon_id') : null,
        );

        $validated = $request->validate([
            'customer_id' => ['sometimes', 'integer', 'exists:customers,id'],
        ]);

        if (! empty($validated['customer_id'])) {
            $stat = $this->clvService->recomputeCustomer((int) $validated['customer_id'], $saloonId);

            return response()->json([
                'message' => 'Customer CLV recomputed successfully.',
                'data' => [
                    'stat' => [
                        'customer_id' => (int) $stat->customer_id,
                        'clv_score' => (int) $stat->clv_score,
                        'clv_tier' => (string) $stat->clv_tier,
                        'lifetime_spend' => (float) $stat->lifetime_spend,
                        'visit_count' => (int) $stat->visit_count,
                        'computed_at' => $stat->computed_at?->toISOString(),
                    ],
                ],
            ]);
        }

        $result = $this->clvService->recomputeForSalon($saloonId);

        return response()->json([
            'message' => 'Salon CLV recomputed successfully.',
            'data' => $result,
        ]);
    }
}
