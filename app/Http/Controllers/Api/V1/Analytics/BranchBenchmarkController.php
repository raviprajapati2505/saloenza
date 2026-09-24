<?php

namespace App\Http\Controllers\Api\V1\Analytics;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Analytics\BranchBenchmarkService;
use App\Support\EnsuresPermission;
use App\Support\Tenant\TenantScope;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BranchBenchmarkController extends Controller
{
    public function __construct(
        private readonly BranchBenchmarkService $benchmarks,
    ) {
    }

    public function compare(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        EnsuresPermission::any($user, ['analytics.benchmark.view', 'analytics.view']);

        $validated = $request->validate([
            'from' => ['required', 'date'],
            'to' => ['required', 'date', 'after_or_equal:from'],
        ]);

        $saloonId = (int) TenantScope::resolveSaloonFilter($user, null);

        return response()->json([
            'message' => 'Branch benchmarks fetched successfully.',
            'data' => [
                'comparison' => $this->benchmarks->compare(
                    $saloonId,
                    (string) $validated['from'],
                    (string) $validated['to'],
                ),
            ],
        ]);
    }
}
