<?php

namespace App\Http\Controllers\Api\V1\Customer;

use App\Http\Controllers\Controller;
use App\Models\RetentionCohort;
use App\Models\RetentionPolicy;
use App\Models\User;
use App\Services\Customer\RetentionService;
use App\Support\Branch\BranchScope;
use App\Support\EnsuresPermission;
use App\Support\Tenant\TenantScope;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RetentionController extends Controller
{
    public function __construct(
        private readonly RetentionService $retention,
    ) {
    }

    public function policiesIndex(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        EnsuresPermission::one($user, 'crm.retention.view');

        $saloonId = TenantScope::resolveSaloonId(
            $user,
            $request->filled('saloon_id') ? (int) $request->integer('saloon_id') : null,
        );

        $policies = RetentionPolicy::query()
            ->where('saloon_id', $saloonId)
            ->orderByDesc('is_active')
            ->orderBy('id')
            ->get()
            ->map(fn (RetentionPolicy $p) => $this->retention->policyPayload($p))
            ->all();

        return response()->json([
            'message' => 'Retention policies fetched successfully.',
            'data' => [
                'policies' => $policies,
            ],
        ]);
    }

    public function policiesStore(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        EnsuresPermission::one($user, 'crm.retention.manage');

        $saloonId = TenantScope::resolveSaloonId(
            $user,
            $request->filled('saloon_id') ? (int) $request->integer('saloon_id') : null,
        );

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'at_risk_after_days' => ['sometimes', 'integer', 'min:1', 'max:365'],
            'lapsed_after_days' => ['sometimes', 'integer', 'min:1', 'max:730'],
            'lost_after_days' => ['sometimes', 'integer', 'min:1', 'max:1095'],
            'min_visits_required' => ['sometimes', 'integer', 'min:0', 'max:50'],
            'exclude_tag_ids' => ['sometimes', 'array'],
            'exclude_tag_ids.*' => ['integer'],
            'channels_allowed' => ['sometimes', 'array'],
            'channels_allowed.*' => ['string', 'in:email,sms,manual'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $policy = RetentionPolicy::query()->create([
            'saloon_id' => $saloonId,
            'name' => trim((string) $validated['name']),
            'at_risk_after_days' => (int) ($validated['at_risk_after_days'] ?? 40),
            'lapsed_after_days' => (int) ($validated['lapsed_after_days'] ?? 60),
            'lost_after_days' => (int) ($validated['lost_after_days'] ?? 120),
            'min_visits_required' => (int) ($validated['min_visits_required'] ?? 1),
            'exclude_tag_ids' => $validated['exclude_tag_ids'] ?? [],
            'channels_allowed' => $validated['channels_allowed'] ?? ['email', 'manual'],
            'is_active' => array_key_exists('is_active', $validated) ? (bool) $validated['is_active'] : true,
        ]);

        return response()->json([
            'message' => 'Retention policy created successfully.',
            'data' => [
                'policy' => $this->retention->policyPayload($policy),
            ],
        ], 201);
    }

    public function policiesUpdate(Request $request, RetentionPolicy $policy): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        EnsuresPermission::one($user, 'crm.retention.manage');
        TenantScope::ensureSaloonAccess($user, (int) $policy->saloon_id);

        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:120'],
            'at_risk_after_days' => ['sometimes', 'integer', 'min:1', 'max:365'],
            'lapsed_after_days' => ['sometimes', 'integer', 'min:1', 'max:730'],
            'lost_after_days' => ['sometimes', 'integer', 'min:1', 'max:1095'],
            'min_visits_required' => ['sometimes', 'integer', 'min:0', 'max:50'],
            'exclude_tag_ids' => ['sometimes', 'array'],
            'exclude_tag_ids.*' => ['integer'],
            'channels_allowed' => ['sometimes', 'array'],
            'channels_allowed.*' => ['string', 'in:email,sms,manual'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $policy->fill($validated)->save();

        return response()->json([
            'message' => 'Retention policy updated successfully.',
            'data' => [
                'policy' => $this->retention->policyPayload($policy->fresh()),
            ],
        ]);
    }

    public function customers(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        EnsuresPermission::one($user, 'crm.retention.view');

        $saloonId = TenantScope::resolveSaloonId(
            $user,
            $request->filled('saloon_id') ? (int) $request->integer('saloon_id') : null,
        );

        $validated = $request->validate([
            'status' => ['required', 'string', 'in:active,at_risk,lapsed,lost'],
            'branch_id' => ['sometimes', 'integer', 'exists:saloon_branches,id'],
            'classify' => ['sometimes', 'boolean'],
            'limit' => ['sometimes', 'integer', 'min:1', 'max:500'],
        ]);

        $branchId = $user->isBranchScopedActor() && $user->branch_id
            ? (int) $user->branch_id
            : ($request->filled('branch_id') ? (int) $request->integer('branch_id') : null);

        if ($branchId !== null && $user->isBranchScopedActor()) {
            BranchScope::ensureSameBranch(
                $user,
                $branchId,
                'You can only view retention for your current branch.',
            );
        }

        if ($request->boolean('classify')) {
            $this->retention->classifyCustomers($saloonId);
        }

        $customers = $this->retention->listByStatus(
            $saloonId,
            (string) $validated['status'],
            $branchId,
            (int) ($validated['limit'] ?? 200),
        );

        return response()->json([
            'message' => 'Retention customers fetched successfully.',
            'data' => [
                'customers' => $customers,
                'status' => $validated['status'],
            ],
        ]);
    }

    public function classify(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        EnsuresPermission::one($user, 'crm.retention.manage');

        $saloonId = TenantScope::resolveSaloonId(
            $user,
            $request->filled('saloon_id') ? (int) $request->integer('saloon_id') : null,
        );

        $result = $this->retention->classifyCustomers($saloonId);

        return response()->json([
            'message' => 'Customers classified successfully.',
            'data' => $result,
        ]);
    }

    public function cohortsIndex(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        EnsuresPermission::one($user, 'crm.retention.view');

        $saloonId = TenantScope::resolveSaloonId(
            $user,
            $request->filled('saloon_id') ? (int) $request->integer('saloon_id') : null,
        );

        $cohorts = RetentionCohort::query()
            ->where('saloon_id', $saloonId)
            ->withCount('members')
            ->latest('id')
            ->limit(50)
            ->get()
            ->map(fn (RetentionCohort $c) => [
                'id' => (int) $c->id,
                'name' => $c->name,
                'status' => $c->status,
                'template_channel' => $c->template_channel,
                'stats_json' => $c->stats_json,
                'members_count' => (int) $c->members_count,
                'created_at' => $c->created_at?->toISOString(),
            ])
            ->all();

        return response()->json([
            'message' => 'Retention cohorts fetched successfully.',
            'data' => [
                'cohorts' => $cohorts,
            ],
        ]);
    }

    public function cohortsStore(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        EnsuresPermission::one($user, 'crm.retention.manage');

        $saloonId = TenantScope::resolveSaloonId(
            $user,
            $request->filled('saloon_id') ? (int) $request->integer('saloon_id') : null,
        );

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:160'],
            'lapse_status' => ['required', 'string', 'in:at_risk,lapsed,lost'],
            'policy_id' => ['sometimes', 'nullable', 'integer', 'exists:retention_policies,id'],
            'branch_id' => ['sometimes', 'nullable', 'integer', 'exists:saloon_branches,id'],
            'customer_ids' => ['sometimes', 'array'],
            'customer_ids.*' => ['integer', 'exists:customers,id'],
            'template_channel' => ['sometimes', 'string', 'in:email,sms,manual'],
            'note' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'classify' => ['sometimes', 'boolean'],
        ]);

        if ($request->boolean('classify', true)) {
            $this->retention->classifyCustomers($saloonId);
        }

        $cohort = $this->retention->createCohort($saloonId, $user, $validated);

        return response()->json([
            'message' => 'Retention cohort created successfully.',
            'data' => [
                'cohort' => $this->retention->cohortPayload($cohort),
            ],
        ], 201);
    }

    public function cohortsShow(Request $request, RetentionCohort $cohort): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        EnsuresPermission::one($user, 'crm.retention.view');
        TenantScope::ensureSaloonAccess($user, (int) $cohort->saloon_id);

        return response()->json([
            'message' => 'Retention cohort fetched successfully.',
            'data' => [
                'cohort' => $this->retention->cohortPayload($cohort),
            ],
        ]);
    }

    public function cohortsSend(Request $request, RetentionCohort $cohort): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        EnsuresPermission::one($user, 'crm.retention.manage');
        TenantScope::ensureSaloonAccess($user, (int) $cohort->saloon_id);

        $validated = $request->validate([
            'member_ids' => ['sometimes', 'array'],
            'member_ids.*' => ['integer'],
            'deliver' => ['sometimes', 'boolean'],
        ]);

        $result = $this->retention->markMembersSent(
            $cohort,
            $validated['member_ids'] ?? null,
            $request->boolean('deliver', false),
        );

        return response()->json([
            'message' => 'Cohort send processed successfully.',
            'data' => [
                'result' => $result,
                'cohort' => $this->retention->cohortPayload($cohort->fresh()),
            ],
        ]);
    }
}
