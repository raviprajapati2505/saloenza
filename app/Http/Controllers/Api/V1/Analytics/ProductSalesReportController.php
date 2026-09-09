<?php

namespace App\Http\Controllers\Api\V1\Analytics;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Analytics\ProductSalesReportService;
use App\Support\Branch\BranchScope;
use App\Support\EnsuresPermission;
use App\Support\Tenant\TenantScope;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProductSalesReportController extends Controller
{
    public function __construct(
        private readonly ProductSalesReportService $report,
    ) {}

    public function summary(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        EnsuresPermission::one($user, 'inventory.view');

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
                'You can only view product sales for your current branch.',
            );
        }

        return response()->json([
            'message' => 'Product sales report fetched successfully.',
            'data' => [
                'summary' => $this->report->summary(
                    $saloonId,
                    $branchId,
                    (string) $validated['from'],
                    (string) $validated['to'],
                ),
            ],
        ]);
    }
}
