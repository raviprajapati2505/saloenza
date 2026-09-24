<?php

namespace App\Http\Controllers\Api\V1\Inventory;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Inventory\InventoryIntelligenceService;
use App\Support\Branch\BranchScope;
use App\Support\EnsuresPermission;
use App\Support\Tenant\TenantScope;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class InventoryIntelligenceController extends Controller
{
    public function __construct(
        private readonly InventoryIntelligenceService $intelligence,
    ) {
    }

    public function classifications(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        EnsuresPermission::one($user, 'inventory.view');

        $saloonId = (int) TenantScope::resolveSaloonFilter($user, null);
        $branchId = $this->resolveOptionalBranchId($user, $request);

        return response()->json([
            'message' => 'Inventory classifications fetched successfully.',
            'data' => [
                'classifications' => $this->intelligence->listClassifications($saloonId, $branchId),
            ],
        ]);
    }

    public function stockoutRisks(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        EnsuresPermission::one($user, 'inventory.view');

        $saloonId = (int) TenantScope::resolveSaloonFilter($user, null);
        $branchId = $this->resolveOptionalBranchId($user, $request);

        return response()->json([
            'message' => 'Stockout risks fetched successfully.',
            'data' => [
                'risks' => $this->intelligence->stockoutRisks($saloonId, $branchId),
            ],
        ]);
    }

    public function alerts(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        EnsuresPermission::one($user, 'inventory.view');

        $saloonId = (int) TenantScope::resolveSaloonFilter($user, null);
        $branchId = $this->resolveOptionalBranchId($user, $request);

        return response()->json([
            'message' => 'Inventory alerts fetched successfully.',
            'data' => [
                'alerts' => $this->intelligence->openAlerts($saloonId, $branchId),
            ],
        ]);
    }

    public function recompute(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        EnsuresPermission::one($user, 'inventory.manage');

        $saloonId = (int) TenantScope::resolveSaloonFilter($user, null);
        $result = $this->intelligence->recomputeMetrics($saloonId);

        return response()->json([
            'message' => 'Inventory intelligence recomputed successfully.',
            'data' => $result,
        ]);
    }

    private function resolveOptionalBranchId(User $user, Request $request): ?int
    {
        if ($user->isBranchScopedActor() && $user->branch_id) {
            return (int) $user->branch_id;
        }

        if (! $request->filled('branch_id')) {
            return null;
        }

        $branchId = (int) $request->integer('branch_id');
        if ($user->isBranchScopedActor()) {
            BranchScope::ensureSameBranch(
                $user,
                $branchId,
                'You can only access inventory intelligence for your current branch.',
            );
        }

        return $branchId;
    }
}
