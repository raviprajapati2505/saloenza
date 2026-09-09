<?php

namespace App\Http\Controllers\Api\V1\Affiliate;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Affiliate\StoreAffiliateWithdrawalRequest;
use App\Http\Resources\Api\V1\Affiliate\AffiliateCommissionResource;
use App\Http\Resources\Api\V1\Affiliate\AffiliatePartnerResource;
use App\Http\Resources\Api\V1\Affiliate\AffiliateReferralResource;
use App\Http\Resources\Api\V1\Affiliate\AffiliateWithdrawalRequestResource;
use App\Models\AffiliateCommission;
use App\Models\AffiliatePartner;
use App\Models\AffiliateReferral;
use App\Models\AffiliateWithdrawalRequest;
use App\Models\Saloon;
use App\Models\User;
use App\Services\Affiliate\AffiliateCommissionService;
use App\Services\Notifications\PlatformAdminNotifier;
use App\Support\Subscription\SubscriptionEntitlements;
use App\Support\Api\ListQuery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\HttpException;

class AffiliatePortalController extends Controller
{
    public function __construct(
        private readonly AffiliateCommissionService $commissionService,
        private readonly PlatformAdminNotifier $platformAdminNotifier,
        private readonly SubscriptionEntitlements $subscriptionEntitlements,
    ) {
    }

    public function dashboard(Request $request): JsonResponse
    {
        $partner = $this->resolvePartner($request->user());
        $this->commissionService->releaseMaturedCommissions($partner);

        $commissions = AffiliateCommission::query()->where('affiliate_partner_id', $partner->id);

        return response()->json([
            'message' => 'Affiliate dashboard fetched successfully.',
            'data' => [
                'affiliate_partner' => (new AffiliatePartnerResource($partner->load('user')))->resolve(),
                'summary' => [
                    'referrals_count' => AffiliateReferral::query()->where('affiliate_partner_id', $partner->id)->count(),
                    'saloons_count' => $partner->saloons()->count(),
                    'locked_commission' => (float) (clone $commissions)->where('status', AffiliateCommission::STATUS_LOCKED)->sum('commission_amount'),
                    'available_commission' => (float) (clone $commissions)->where('status', AffiliateCommission::STATUS_AVAILABLE)->sum('commission_amount'),
                    'requested_commission' => (float) (clone $commissions)->where('status', AffiliateCommission::STATUS_REQUESTED)->sum('commission_amount'),
                    'paid_commission' => (float) (clone $commissions)->where('status', AffiliateCommission::STATUS_PAID)->sum('commission_amount'),
                ],
                'recent_commissions' => AffiliateCommissionResource::collection(
                    AffiliateCommission::query()
                        ->with(['saloon', 'upgradeOrder'])
                        ->where('affiliate_partner_id', $partner->id)
                        ->latest('id')
                        ->limit(10)
                        ->get(),
                ),
                'recent_referrals' => AffiliateReferralResource::collection(
                    AffiliateReferral::query()
                        ->with(['saloon', 'owner'])
                        ->where('affiliate_partner_id', $partner->id)
                        ->latest('id')
                        ->limit(10)
                        ->get(),
                ),
            ],
        ]);
    }

    public function referrals(Request $request): JsonResponse
    {
        $partner = $this->resolvePartner($request->user());
        $validated = ListQuery::validate($request, [
            'source' => ['sometimes', 'string'],
            'subscription_lifecycle' => ['sometimes', 'string', 'in:active,expiring,expiring_critical,expired,locked,activation_pending'],
        ]);

        $saloonIds = AffiliateReferral::query()
            ->where('affiliate_partner_id', $partner->id)
            ->pluck('saloon_id')
            ->filter()
            ->unique()
            ->values()
            ->all();

        $subscriptionSummary = $this->subscriptionEntitlements->summarizeSaloonSubscriptionLifecycles($saloonIds);

        $query = AffiliateReferral::query()
            ->with(['saloon', 'owner'])
            ->where('affiliate_partner_id', $partner->id)
            ->latest('id');

        if (! empty($validated['subscription_lifecycle'])) {
            $filter = (string) $validated['subscription_lifecycle'];
            $matchingSaloonIds = collect($saloonIds)
                ->filter(function (int $saloonId) use ($filter): bool {
                    $salon = Saloon::query()->find($saloonId);
                    if ($salon === null) {
                        return false;
                    }
                    $lifecycle = $this->subscriptionEntitlements->salonSubscriptionSummary($salon)['lifecycle'];

                    return match ($filter) {
                        'active' => $lifecycle === 'active',
                        'expiring' => in_array($lifecycle, ['expiring_month', 'expiring_soon', 'expiring_critical'], true),
                        'expiring_critical' => $lifecycle === 'expiring_critical',
                        'expired' => $lifecycle === 'expired',
                        'locked' => $lifecycle === 'locked',
                        'activation_pending' => $lifecycle === 'activation_pending',
                        default => true,
                    };
                })
                ->values()
                ->all();

            $query->whereIn('saloon_id', $matchingSaloonIds !== [] ? $matchingSaloonIds : [0]);
        }

        if (! empty($validated['source'])) {
            $query->where('source', (string) $validated['source']);
        }

        if (! empty($validated['search'])) {
            $search = (string) $validated['search'];
            $query->where(function ($builder) use ($search): void {
                $builder->where('referral_code', 'like', "%{$search}%")
                    ->orWhereHas('saloon', fn ($q) => $q->where('name', 'like', "%{$search}%")->orWhere('phone', 'like', "%{$search}%"))
                    ->orWhereHas('owner', fn ($q) => $q->where('name', 'like', "%{$search}%")->orWhere('email', 'like', "%{$search}%"));
            });
        }

        $paginator = ListQuery::paginate($query, $validated);

        $payload = ListQuery::responsePayload(
            'Affiliate referrals fetched successfully.',
            'referrals',
            $paginator,
            AffiliateReferralResource::class,
        );
        $payload['data']['subscription_summary'] = $subscriptionSummary;

        return response()->json($payload);
    }

    public function commissions(Request $request): JsonResponse
    {
        $partner = $this->resolvePartner($request->user());
        $this->commissionService->releaseMaturedCommissions($partner);
        $validated = ListQuery::validate($request, [
            'status' => ['sometimes', 'string'],
            'type' => ['sometimes', 'string'],
        ]);

        $query = AffiliateCommission::query()
            ->with(['saloon', 'upgradeOrder'])
            ->where('affiliate_partner_id', $partner->id)
            ->latest('id');

        if (! empty($validated['status'])) {
            $query->where('status', (string) $validated['status']);
        }

        if (! empty($validated['type'])) {
            $query->where('type', (string) $validated['type']);
        }

        $paginator = ListQuery::paginate($query, $validated);

        return response()->json(ListQuery::responsePayload(
            'Affiliate commissions fetched successfully.',
            'commissions',
            $paginator,
            AffiliateCommissionResource::class,
        ));
    }

    public function withdrawals(Request $request): JsonResponse
    {
        $partner = $this->resolvePartner($request->user());
        $validated = ListQuery::validate($request, [
            'status' => ['sometimes', 'string'],
        ]);

        $query = AffiliateWithdrawalRequest::query()
            ->where('affiliate_partner_id', $partner->id)
            ->latest('id');

        if (! empty($validated['status'])) {
            $query->where('status', (string) $validated['status']);
        }

        $paginator = ListQuery::paginate($query, $validated);

        return response()->json(ListQuery::responsePayload(
            'Affiliate withdrawals fetched successfully.',
            'withdrawals',
            $paginator,
            AffiliateWithdrawalRequestResource::class,
        ));
    }

    public function requestWithdrawal(StoreAffiliateWithdrawalRequest $request): JsonResponse
    {
        $partner = $this->resolvePartner($request->user());
        $this->commissionService->releaseMaturedCommissions($partner);

        $amount = round((float) $request->validated('amount'), 2);

        $existingPending = AffiliateWithdrawalRequest::query()
            ->where('affiliate_partner_id', $partner->id)
            ->where('status', AffiliateWithdrawalRequest::STATUS_PENDING)
            ->exists();

        if ($existingPending) {
            throw new HttpException(422, 'You already have a pending withdrawal request.');
        }

        $withdrawal = \Illuminate\Support\Facades\DB::transaction(function () use ($partner, $amount, $request): AffiliateWithdrawalRequest {
            $withdrawal = AffiliateWithdrawalRequest::query()->create([
                'affiliate_partner_id' => $partner->id,
                'amount' => $amount,
                'currency' => 'INR',
                'status' => AffiliateWithdrawalRequest::STATUS_PENDING,
                'notes' => $request->validated('notes'),
                'requested_at' => now(),
            ]);

            $this->commissionService->allocateCommissionsForWithdrawal($partner, $withdrawal, $amount);

            return $withdrawal;
        });

        $partnerName = $partner->display_name
            ?: $partner->user?->name
            ?: ($partner->loadMissing('user')->user?->name)
            ?: 'An affiliate partner';
        $formattedAmount = number_format($amount, 2);

        $this->platformAdminNotifier->notify(
            event: 'affiliate.withdrawal.requested',
            title: 'Affiliate withdrawal requested',
            body: "{$partnerName} requested a withdrawal of {$formattedAmount} {$withdrawal->currency}.",
            actionUrl: '/admin/affiliates?tab=withdrawals&status=pending',
            requiredPermissions: ['platform.affiliates.view'],
            meta: [
                'affiliate_partner_id' => $partner->id,
                'withdrawal_id' => $withdrawal->id,
                'amount' => $amount,
            ],
        );

        return response()->json([
            'message' => 'Withdrawal request submitted successfully.',
            'data' => [
                'withdrawal' => (new AffiliateWithdrawalRequestResource($withdrawal))->resolve(),
            ],
        ], 201);
    }

    public function reports(Request $request): JsonResponse
    {
        $partner = $this->resolvePartner($request->user());
        $this->commissionService->releaseMaturedCommissions($partner);

        $validated = $request->validate([
            'from' => ['sometimes', 'nullable', 'date'],
            'to' => ['sometimes', 'nullable', 'date', 'after_or_equal:from'],
        ]);

        $from = ! empty($validated['from']) ? now()->parse((string) $validated['from'])->startOfDay() : now()->subMonths(5)->startOfMonth();
        $to = ! empty($validated['to']) ? now()->parse((string) $validated['to'])->endOfDay() : now()->endOfDay();

        $commissions = AffiliateCommission::query()
            ->with('saloon')
            ->where('affiliate_partner_id', $partner->id)
            ->whereBetween('created_at', [$from, $to])
            ->orderBy('created_at')
            ->get();

        $byType = [
            'onboarding' => [
                'count' => $commissions->where('type', AffiliateCommission::TYPE_ONBOARDING)->count(),
                'amount' => (float) $commissions->where('type', AffiliateCommission::TYPE_ONBOARDING)->sum('commission_amount'),
            ],
            'renewal' => [
                'count' => $commissions->where('type', AffiliateCommission::TYPE_RENEWAL)->count(),
                'amount' => (float) $commissions->where('type', AffiliateCommission::TYPE_RENEWAL)->sum('commission_amount'),
            ],
        ];

        $byStatus = [];
        foreach ([
            AffiliateCommission::STATUS_LOCKED,
            AffiliateCommission::STATUS_AVAILABLE,
            AffiliateCommission::STATUS_REQUESTED,
            AffiliateCommission::STATUS_PAID,
            AffiliateCommission::STATUS_VOID,
        ] as $status) {
            $byStatus[$status] = [
                'count' => $commissions->where('status', $status)->count(),
                'amount' => (float) $commissions->where('status', $status)->sum('commission_amount'),
            ];
        }

        $monthly = $commissions
            ->groupBy(fn (AffiliateCommission $row) => $row->created_at?->format('Y-m') ?? 'unknown')
            ->map(function ($rows, $month) {
                return [
                    'month' => $month,
                    'count' => $rows->count(),
                    'amount' => (float) $rows->sum('commission_amount'),
                    'onboarding_amount' => (float) $rows->where('type', AffiliateCommission::TYPE_ONBOARDING)->sum('commission_amount'),
                    'renewal_amount' => (float) $rows->where('type', AffiliateCommission::TYPE_RENEWAL)->sum('commission_amount'),
                ];
            })
            ->values();

        $topSaloons = $commissions
            ->groupBy('saloon_id')
            ->map(function ($rows) {
                /** @var AffiliateCommission $first */
                $first = $rows->first();

                return [
                    'saloon_id' => $first->saloon_id,
                    'saloon_name' => $first->saloon?->name,
                    'commission_count' => $rows->count(),
                    'commission_amount' => (float) $rows->sum('commission_amount'),
                ];
            })
            ->sortByDesc('commission_amount')
            ->take(10)
            ->values();

        return response()->json([
            'message' => 'Affiliate reports fetched successfully.',
            'data' => [
                'range' => [
                    'from' => $from->toDateString(),
                    'to' => $to->toDateString(),
                ],
                'totals' => [
                    'referrals_count' => AffiliateReferral::query()
                        ->where('affiliate_partner_id', $partner->id)
                        ->whereBetween('referred_at', [$from, $to])
                        ->count(),
                    'commission_count' => $commissions->count(),
                    'commission_amount' => (float) $commissions->sum('commission_amount'),
                    'base_amount' => (float) $commissions->sum('base_amount'),
                ],
                'by_type' => $byType,
                'by_status' => $byStatus,
                'monthly' => $monthly,
                'top_saloons' => $topSaloons,
            ],
        ]);
    }

    private function resolvePartner(?User $user): AffiliatePartner
    {
        $partner = $user?->affiliatePartner;

        if ($partner === null) {
            throw new HttpException(403, 'Your account is not linked to an affiliate partner profile.');
        }

        return $partner;
    }
}
