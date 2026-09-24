<?php

namespace App\Http\Controllers\Api\V1\Analytics;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Analytics\SalonInsightsService;
use App\Support\Branch\BranchScope;
use App\Support\EnsuresPermission;
use App\Support\Tenant\TenantScope;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SalonInsightsController extends Controller
{
    public function __construct(
        private readonly SalonInsightsService $insights,
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        EnsuresPermission::any($user, ['analytics.insights.view', 'analytics.view']);

        $validated = $request->validate([
            'branch_id' => ['sometimes', 'integer', 'exists:saloon_branches,id'],
            'saloon_id' => ['sometimes', 'integer', 'exists:saloons,id'],
        ]);

        $saloonId = TenantScope::resolveSaloonFilter(
            $user,
            $request->filled('saloon_id') ? (int) $request->integer('saloon_id') : null,
        );
        $branchId = $user->isBranchScopedActor() && $user->branch_id
            ? (int) $user->branch_id
            : ($request->filled('branch_id') ? (int) $request->integer('branch_id') : null);

        if ($branchId !== null && $user->isBranchScopedActor()) {
            BranchScope::ensureSameBranch(
                $user,
                $branchId,
                'You can only view insights for your current branch.',
            );
        }

        if ($saloonId === null) {
            return response()->json([
                'message' => 'Saloon is required to compute insights.',
                'data' => [
                    'insights' => $this->insights->build(null, $branchId),
                ],
            ], 422);
        }

        $payload = $this->insights->build($saloonId, $branchId);

        return response()->json([
            'message' => 'Salon insights fetched successfully.',
            'data' => [
                'insights' => $payload,
            ],
        ]);
    }
}
