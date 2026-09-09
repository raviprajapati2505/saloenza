<?php

namespace App\Http\Controllers\Api\V1\Analytics;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Analytics\SalonAnalyticsService;
use App\Support\Branch\BranchScope;
use App\Support\EnsuresPermission;
use App\Support\Tenant\TenantScope;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SalonAnalyticsController extends Controller
{
    public function __construct(
        private readonly SalonAnalyticsService $analytics,
    ) {
    }

    public function summary(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        EnsuresPermission::one($user, 'analytics.view');

        $validated = $request->validate([
            'from' => ['required', 'date'],
            'to' => ['required', 'date', 'after_or_equal:from'],
            'branch_id' => ['sometimes', 'integer', 'exists:saloon_branches,id'],
        ]);

        $saloonId = TenantScope::resolveSaloonFilter($user, null);
        $branchId = $user->isBranchScopedActor() && $user->branch_id
            ? (int) $user->branch_id
            : ($request->filled('branch_id') ? (int) $request->integer('branch_id') : null);

        if ($branchId !== null && $user->isBranchScopedActor()) {
            BranchScope::ensureSameBranch(
                $user,
                $branchId,
                'You can only view analytics for your current branch.',
            );
        }

        $summary = $this->analytics->summary(
            $saloonId,
            $branchId,
            (string) $validated['from'],
            (string) $validated['to'],
        );

        return response()->json([
            'message' => 'Analytics summary fetched successfully.',
            'data' => [
                'summary' => $summary,
            ],
        ]);
    }
}
