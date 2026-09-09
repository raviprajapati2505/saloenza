<?php

namespace App\Http\Controllers\Api\V1\Queue;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Queue\LiveQueueService;
use App\Support\EnsuresPermission;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LiveQueueController extends Controller
{
    public function __construct(
        private readonly LiveQueueService $queue,
    ) {}

    public function index(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        EnsuresPermission::one($user, 'queue.view');

        $validated = $request->validate([
            'branch_id' => ['sometimes', 'integer', 'exists:saloon_branches,id'],
            'date' => ['sometimes', 'date'],
        ]);

        $snapshot = $this->queue->snapshot(
            $user,
            isset($validated['branch_id']) ? (int) $validated['branch_id'] : null,
            isset($validated['date']) ? now()->parse($validated['date']) : null,
        );

        return response()->json([
            'message' => 'Live queue fetched successfully.',
            'data' => $snapshot,
        ]);
    }
}
