<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\StoreAffiliatePartnerRequest;
use App\Http\Requests\Api\V1\Admin\UpdateAffiliatePartnerRequest;
use App\Http\Resources\Api\V1\Affiliate\AffiliatePartnerResource;
use App\Http\Resources\Api\V1\Affiliate\AffiliateWithdrawalRequestResource;
use App\Models\AffiliateCommission;
use App\Models\AffiliatePartner;
use App\Models\AffiliateWithdrawalRequest;
use App\Models\Role;
use App\Models\User;
use App\Support\Affiliate\AffiliateCodeGenerator;
use App\Support\Api\ListQuery;
use App\Support\Role\RoleCodes;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Symfony\Component\HttpKernel\Exception\HttpException;

class AdminAffiliatePartnerController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $validated = ListQuery::validate($request, [
            'status' => ['sometimes', 'string'],
        ]);

        $query = AffiliatePartner::query()
            ->with('user')
            ->withCount(['referrals', 'commissions', 'withdrawalRequests'])
            ->latest('id');

        if (! empty($validated['status'])) {
            $query->where('status', (string) $validated['status']);
        }

        if (! empty($validated['search'])) {
            $search = (string) $validated['search'];
            $query->where(function ($builder) use ($search): void {
                $builder->where('code', 'like', "%{$search}%")
                    ->orWhere('display_name', 'like', "%{$search}%")
                    ->orWhereHas('user', function ($userQuery) use ($search): void {
                        $userQuery->where('name', 'like', "%{$search}%")
                            ->orWhere('email', 'like', "%{$search}%")
                            ->orWhere('phone', 'like', "%{$search}%");
                    });
            });
        }

        $paginator = ListQuery::paginate($query, $validated);

        return response()->json(ListQuery::responsePayload(
            'Affiliate partners fetched successfully.',
            'affiliate_partners',
            $paginator,
            AffiliatePartnerResource::class,
        ));
    }

    public function store(StoreAffiliatePartnerRequest $request): JsonResponse
    {
        $payload = $request->validated();
        $role = Role::findByCode(RoleCodes::AFFILIATE_PARTNER);

        if ($role === null) {
            throw new RuntimeException('Affiliate partner role is not configured. Run RoleSeeder.');
        }

        $partner = DB::transaction(function () use ($payload, $role): AffiliatePartner {
            $user = User::query()->create([
                'name' => trim((string) $payload['name']),
                'email' => strtolower(trim((string) $payload['email'])),
                'phone' => trim((string) $payload['phone']),
                'password' => (string) $payload['password'],
                'role_id' => $role->id,
                'is_active' => true,
                'onboarding_completed_at' => now(),
            ]);

            return AffiliatePartner::query()->create([
                'user_id' => $user->id,
                'code' => strtoupper(trim((string) ($payload['code'] ?? AffiliateCodeGenerator::generate((string) $payload['name'])))),
                'display_name' => isset($payload['display_name']) ? trim((string) $payload['display_name']) : trim((string) $payload['name']),
                'status' => $payload['status'] ?? AffiliatePartner::STATUS_ACTIVE,
                'onboarding_commission_rate' => $payload['onboarding_commission_rate'] ?? 12,
                'renewal_commission_rate' => $payload['renewal_commission_rate'] ?? 5,
                'commission_lock_days' => $payload['commission_lock_days'] ?? 30,
                'payout_method' => $payload['payout_method'] ?? null,
                'payout_details' => $payload['payout_details'] ?? null,
                'notes' => $payload['notes'] ?? null,
                'joined_at' => now(),
                'activated_at' => ($payload['status'] ?? AffiliatePartner::STATUS_ACTIVE) === AffiliatePartner::STATUS_ACTIVE ? now() : null,
            ]);
        });

        return response()->json([
            'message' => 'Affiliate partner created successfully.',
            'data' => [
                'affiliate_partner' => (new AffiliatePartnerResource($partner->load('user')))->resolve(),
            ],
        ], 201);
    }

    public function approve(AffiliatePartner $affiliatePartner): JsonResponse
    {
        if (! in_array($affiliatePartner->status, [
            AffiliatePartner::STATUS_PENDING,
            AffiliatePartner::STATUS_SUSPENDED,
            AffiliatePartner::STATUS_REJECTED,
        ], true)) {
            throw new HttpException(422, 'Only pending, suspended, or rejected partners can be approved.');
        }

        DB::transaction(function () use ($affiliatePartner): void {
            $affiliatePartner->update([
                'status' => AffiliatePartner::STATUS_ACTIVE,
                'activated_at' => $affiliatePartner->activated_at ?? now(),
            ]);

            $affiliatePartner->user()->update([
                'is_active' => true,
            ]);
        });

        return response()->json([
            'message' => 'Affiliate partner approved successfully.',
            'data' => [
                'affiliate_partner' => (new AffiliatePartnerResource(
                    $affiliatePartner->fresh()->load('user'),
                ))->resolve(),
            ],
        ]);
    }

    public function reject(Request $request, AffiliatePartner $affiliatePartner): JsonResponse
    {
        if (! in_array($affiliatePartner->status, [
            AffiliatePartner::STATUS_PENDING,
            AffiliatePartner::STATUS_ACTIVE,
            AffiliatePartner::STATUS_SUSPENDED,
        ], true)) {
            throw new HttpException(422, 'Only pending, active, or suspended partners can be rejected.');
        }

        $reason = trim((string) ($request->input('reason') ?? 'Application rejected by admin.'));

        DB::transaction(function () use ($affiliatePartner, $reason): void {
            $affiliatePartner->update([
                'status' => AffiliatePartner::STATUS_REJECTED,
                'notes' => trim(($affiliatePartner->notes ? $affiliatePartner->notes."\n" : '').'Rejection: '.$reason),
            ]);

            $affiliatePartner->user()->update([
                'is_active' => false,
            ]);
        });

        return response()->json([
            'message' => 'Affiliate partner rejected.',
            'data' => [
                'affiliate_partner' => (new AffiliatePartnerResource(
                    $affiliatePartner->fresh()->load('user'),
                ))->resolve(),
            ],
        ]);
    }

    public function update(UpdateAffiliatePartnerRequest $request, AffiliatePartner $affiliatePartner): JsonResponse
    {
        $payload = $request->validated();

        DB::transaction(function () use ($affiliatePartner, $payload): void {
            $userUpdates = [];

            if (array_key_exists('name', $payload)) {
                $userUpdates['name'] = trim((string) $payload['name']);
            }
            if (array_key_exists('email', $payload)) {
                $userUpdates['email'] = strtolower(trim((string) $payload['email']));
            }
            if (array_key_exists('phone', $payload)) {
                $userUpdates['phone'] = trim((string) $payload['phone']);
            }

            if ($userUpdates !== []) {
                $affiliatePartner->user()->update($userUpdates);
            }

            $updates = [];
            foreach ([
                'display_name',
                'status',
                'onboarding_commission_rate',
                'renewal_commission_rate',
                'commission_lock_days',
                'payout_method',
                'payout_details',
                'notes',
            ] as $field) {
                if (array_key_exists($field, $payload)) {
                    $updates[$field] = $payload[$field];
                }
            }

            if (array_key_exists('code', $payload)) {
                $updates['code'] = strtoupper(trim((string) $payload['code']));
            }

            if (($payload['status'] ?? null) === AffiliatePartner::STATUS_ACTIVE && $affiliatePartner->activated_at === null) {
                $updates['activated_at'] = now();
            }

            if ($updates !== []) {
                $affiliatePartner->update($updates);
            }

            if (($payload['status'] ?? null) === AffiliatePartner::STATUS_ACTIVE) {
                $affiliatePartner->user()->update(['is_active' => true]);
            }

            if (in_array($payload['status'] ?? null, [
                AffiliatePartner::STATUS_SUSPENDED,
                AffiliatePartner::STATUS_REJECTED,
            ], true)) {
                $affiliatePartner->user()->update(['is_active' => false]);
            }
        });

        return response()->json([
            'message' => 'Affiliate partner updated successfully.',
            'data' => [
                'affiliate_partner' => (new AffiliatePartnerResource($affiliatePartner->fresh()->load('user')))->resolve(),
            ],
        ]);
    }

    public function withdrawals(Request $request): JsonResponse
    {
        $validated = ListQuery::validate($request, [
            'status' => ['sometimes', 'string'],
            'affiliate_partner_id' => ['sometimes', 'integer'],
        ]);

        $query = AffiliateWithdrawalRequest::query()
            ->with(['affiliatePartner.user'])
            ->latest('id');

        if (! empty($validated['status'])) {
            $query->where('status', (string) $validated['status']);
        }

        if (! empty($validated['affiliate_partner_id'])) {
            $query->where('affiliate_partner_id', (int) $validated['affiliate_partner_id']);
        }

        $paginator = ListQuery::paginate($query, $validated);

        return response()->json(ListQuery::responsePayload(
            'Affiliate withdrawal requests fetched successfully.',
            'withdrawals',
            $paginator,
            AffiliateWithdrawalRequestResource::class,
        ));
    }

    public function approveWithdrawal(Request $request, AffiliateWithdrawalRequest $withdrawal): JsonResponse
    {
        if ($withdrawal->status !== AffiliateWithdrawalRequest::STATUS_PENDING) {
            throw new HttpException(422, 'Only pending withdrawal requests can be approved.');
        }

        $withdrawal->update([
            'status' => AffiliateWithdrawalRequest::STATUS_APPROVED,
            'reviewed_at' => now(),
            'notes' => $request->input('notes') ?: $withdrawal->notes,
        ]);

        return response()->json([
            'message' => 'Withdrawal request approved.',
            'data' => [
                'withdrawal' => (new AffiliateWithdrawalRequestResource(
                    $withdrawal->fresh()->load('affiliatePartner.user'),
                ))->resolve(),
            ],
        ]);
    }

    public function rejectWithdrawal(Request $request, AffiliateWithdrawalRequest $withdrawal): JsonResponse
    {
        if (! in_array($withdrawal->status, [
            AffiliateWithdrawalRequest::STATUS_PENDING,
            AffiliateWithdrawalRequest::STATUS_APPROVED,
        ], true)) {
            throw new HttpException(422, 'Only pending or approved withdrawal requests can be rejected.');
        }

        DB::transaction(function () use ($withdrawal, $request): void {
            AffiliateCommission::query()
                ->where('affiliate_withdrawal_request_id', $withdrawal->id)
                ->where('status', AffiliateCommission::STATUS_REQUESTED)
                ->update([
                    'status' => AffiliateCommission::STATUS_AVAILABLE,
                    'affiliate_withdrawal_request_id' => null,
                    'available_at' => now(),
                ]);

            $withdrawal->update([
                'status' => AffiliateWithdrawalRequest::STATUS_REJECTED,
                'reviewed_at' => now(),
                'notes' => trim(($withdrawal->notes ? $withdrawal->notes.' ' : '').($request->input('reason') ?? 'Rejected by admin.')),
            ]);
        });

        return response()->json([
            'message' => 'Withdrawal request rejected.',
            'data' => [
                'withdrawal' => (new AffiliateWithdrawalRequestResource(
                    $withdrawal->fresh()->load('affiliatePartner.user'),
                ))->resolve(),
            ],
        ]);
    }

    public function markWithdrawalPaid(Request $request, AffiliateWithdrawalRequest $withdrawal): JsonResponse
    {
        if (! in_array($withdrawal->status, [
            AffiliateWithdrawalRequest::STATUS_PENDING,
            AffiliateWithdrawalRequest::STATUS_APPROVED,
        ], true)) {
            throw new HttpException(422, 'Only pending or approved withdrawal requests can be marked paid.');
        }

        $reference = $request->input('payout_reference');

        DB::transaction(function () use ($withdrawal, $reference, $request): void {
            AffiliateCommission::query()
                ->where('affiliate_withdrawal_request_id', $withdrawal->id)
                ->where('status', AffiliateCommission::STATUS_REQUESTED)
                ->update([
                    'status' => AffiliateCommission::STATUS_PAID,
                    'withdrawn_at' => now(),
                ]);

            $withdrawal->update([
                'status' => AffiliateWithdrawalRequest::STATUS_PAID,
                'payout_reference' => $reference ?: $withdrawal->payout_reference,
                'reviewed_at' => $withdrawal->reviewed_at ?? now(),
                'paid_at' => now(),
                'notes' => $request->input('notes') ?: $withdrawal->notes,
            ]);
        });

        return response()->json([
            'message' => 'Withdrawal marked as paid.',
            'data' => [
                'withdrawal' => (new AffiliateWithdrawalRequestResource(
                    $withdrawal->fresh()->load('affiliatePartner.user'),
                ))->resolve(),
            ],
        ]);
    }
}
