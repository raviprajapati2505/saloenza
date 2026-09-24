<?php

namespace App\Http\Controllers\Api\V1\Commission;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\Commission\CommissionPayoutPeriodResource;
use App\Models\CommissionLineItem;
use App\Models\CommissionPayoutPeriod;
use App\Models\User;
use App\Support\Api\ListQuery;
use App\Support\EnsuresPermission;
use App\Support\Tenant\TenantScope;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class CommissionPayoutController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        EnsuresPermission::one($user, 'commissions.view');
        $saloonId = TenantScope::resolveSaloonFilter($user, null);

        $validated = ListQuery::validate($request, [
            'status' => ['sometimes', 'string', Rule::in(['open', 'locked', 'paid'])],
        ]);

        $query = CommissionPayoutPeriod::query()
            ->with(['locker'])
            ->when($saloonId !== null, fn ($q) => $q->where('saloon_id', $saloonId))
            ->orderByDesc('period_start')
            ->orderByDesc('id');

        if (array_key_exists('status', $validated)) {
            $query->where('status', $validated['status']);
        }

        $paginator = ListQuery::paginate($query, $validated);

        return response()->json(ListQuery::responsePayload(
            'Commission payout periods fetched successfully.',
            'periods',
            $paginator,
            CommissionPayoutPeriodResource::class,
        ));
    }

    public function store(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        EnsuresPermission::one($user, 'commissions.manage');

        $validated = $request->validate([
            'period_start' => ['required', 'date'],
            'period_end' => ['required', 'date', 'after_or_equal:period_start'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $saloonId = (int) $user->saloon_id;

        $period = CommissionPayoutPeriod::query()->create([
            'saloon_id' => $saloonId,
            'period_start' => $validated['period_start'],
            'period_end' => $validated['period_end'],
            'status' => 'open',
            'total_commission' => 0,
            'notes' => $validated['notes'] ?? null,
        ]);

        $total = CommissionLineItem::query()
            ->where('saloon_id', $saloonId)
            ->whereBetween('earned_on', [$validated['period_start'], $validated['period_end']])
            ->whereIn('status', ['pending', 'approved'])
            ->sum('commission_amount');

        $period->update(['total_commission' => round((float) $total, 2)]);

        return response()->json([
            'message' => 'Commission payout period created successfully.',
            'data' => [
                'period' => (new CommissionPayoutPeriodResource($period->fresh()->load('locker')))->resolve(),
            ],
        ], 201);
    }

    public function lock(Request $request, CommissionPayoutPeriod $period): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        EnsuresPermission::one($user, 'commissions.approve');
        TenantScope::ensureSaloonAccess($user, (int) $period->saloon_id);

        if ($period->status === 'locked' || $period->status === 'paid') {
            return response()->json([
                'message' => 'This payout period is already locked.',
                'data' => [
                    'period' => (new CommissionPayoutPeriodResource($period->load('locker')))->resolve(),
                ],
            ]);
        }

        DB::transaction(function () use ($period, $user): void {
            $total = CommissionLineItem::query()
                ->where('saloon_id', $period->saloon_id)
                ->whereBetween('earned_on', [
                    $period->period_start->toDateString(),
                    $period->period_end->toDateString(),
                ])
                ->whereIn('status', ['pending', 'approved'])
                ->sum('commission_amount');

            CommissionLineItem::query()
                ->where('saloon_id', $period->saloon_id)
                ->whereBetween('earned_on', [
                    $period->period_start->toDateString(),
                    $period->period_end->toDateString(),
                ])
                ->where('status', 'pending')
                ->update([
                    'status' => 'approved',
                    'payout_period_id' => $period->id,
                ]);

            $period->update([
                'status' => 'locked',
                'total_commission' => round((float) $total, 2),
                'locked_at' => now(),
                'locked_by' => (int) $user->id,
            ]);
        });

        return response()->json([
            'message' => 'Commission payout period locked successfully.',
            'data' => [
                'period' => (new CommissionPayoutPeriodResource($period->fresh()->load('locker')))->resolve(),
            ],
        ]);
    }
}
