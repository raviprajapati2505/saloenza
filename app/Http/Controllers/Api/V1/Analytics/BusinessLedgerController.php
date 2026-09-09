<?php

namespace App\Http\Controllers\Api\V1\Analytics;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Analytics\BusinessLedgerService;
use App\Support\Branch\BranchScope;
use App\Support\EnsuresPermission;
use App\Support\Tenant\TenantScope;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BusinessLedgerController extends Controller
{
    public function __construct(
        private readonly BusinessLedgerService $ledger,
    ) {}

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
                'You can only view the business close for your current branch.',
            );
        }

        return response()->json([
            'message' => 'Business close report fetched successfully.',
            'data' => [
                'summary' => $this->ledger->summary(
                    $saloonId,
                    $branchId,
                    (string) $validated['from'],
                    (string) $validated['to'],
                ),
            ],
        ]);
    }
}
