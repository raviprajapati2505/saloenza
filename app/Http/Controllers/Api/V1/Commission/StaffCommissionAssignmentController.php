<?php

namespace App\Http\Controllers\Api\V1\Commission;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\Commission\StaffCommissionAssignmentResource;
use App\Models\CommissionScheme;
use App\Models\StaffCommissionAssignment;
use App\Models\User;
use App\Support\Api\ListQuery;
use App\Support\Branch\BranchScope;
use App\Support\EnsuresPermission;
use App\Support\Tenant\TenantScope;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class StaffCommissionAssignmentController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        EnsuresPermission::one($user, 'commissions.view');
        $saloonId = TenantScope::resolveSaloonFilter($user, null);

        $validated = ListQuery::validate($request, [
            'user_id' => ['sometimes', 'integer', 'exists:users,id'],
            'scheme_id' => ['sometimes', 'integer', 'exists:commission_schemes,id'],
            'branch_id' => ['sometimes', 'integer', 'exists:saloon_branches,id'],
        ]);

        $branchId = $this->resolveBranchFilter($user, $request);

        $query = StaffCommissionAssignment::query()
            ->with(['user', 'scheme', 'branch'])
            ->when($saloonId !== null, fn ($q) => $q->where('saloon_id', $saloonId))
            ->when($branchId !== null, function ($q) use ($branchId): void {
                $q->where(fn ($inner) => $inner->whereNull('branch_id')->orWhere('branch_id', $branchId));
            })
            ->orderByDesc('id');

        if (array_key_exists('user_id', $validated)) {
            $query->where('user_id', (int) $validated['user_id']);
        }

        if (array_key_exists('scheme_id', $validated)) {
            $query->where('scheme_id', (int) $validated['scheme_id']);
        }

        $paginator = ListQuery::paginate($query, $validated);

        return response()->json(ListQuery::responsePayload(
            'Staff commission assignments fetched successfully.',
            'assignments',
            $paginator,
            StaffCommissionAssignmentResource::class,
        ));
    }

    public function store(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        EnsuresPermission::one($user, 'commissions.manage');

        $saloonId = (int) $user->saloon_id;
        $validated = $request->validate([
            'user_id' => [
                'required',
                'integer',
                Rule::exists('users', 'id')->where(fn ($q) => $q->where('saloon_id', $saloonId)),
            ],
            'scheme_id' => [
                'required',
                'integer',
                Rule::exists('commission_schemes', 'id')->where(fn ($q) => $q->where('saloon_id', $saloonId)),
            ],
            'branch_id' => [
                'nullable',
                'integer',
                Rule::exists('saloon_branches', 'id')->where(fn ($q) => $q->where('saloon_id', $saloonId)),
            ],
            'effective_from' => ['nullable', 'date'],
            'effective_to' => ['nullable', 'date', 'after_or_equal:effective_from'],
            'override_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
        ]);

        $scheme = CommissionScheme::query()->findOrFail((int) $validated['scheme_id']);
        TenantScope::ensureSaloonAccess($user, (int) $scheme->saloon_id);

        $branchId = $this->resolveWritableBranch($user, $validated);
        BranchScope::ensureSameBranch(
            $user,
            $branchId,
            'You can only assign commissions for your current branch.',
        );

        $assignment = StaffCommissionAssignment::query()->create([
            'user_id' => (int) $validated['user_id'],
            'saloon_id' => $saloonId,
            'branch_id' => $branchId,
            'scheme_id' => (int) $validated['scheme_id'],
            'effective_from' => $validated['effective_from'] ?? null,
            'effective_to' => $validated['effective_to'] ?? null,
            'override_percent' => $validated['override_percent'] ?? null,
        ]);

        return response()->json([
            'message' => 'Staff assigned to commission scheme successfully.',
            'data' => [
                'assignment' => (new StaffCommissionAssignmentResource(
                    $assignment->load(['user', 'scheme', 'branch'])
                ))->resolve(),
            ],
        ], 201);
    }

    public function update(Request $request, StaffCommissionAssignment $assignment): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        EnsuresPermission::one($user, 'commissions.manage');
        TenantScope::ensureSaloonAccess($user, (int) $assignment->saloon_id);
        BranchScope::ensureSameBranch(
            $user,
            $assignment->branch_id !== null ? (int) $assignment->branch_id : null,
            'You can only manage assignments for your current branch.',
        );

        $saloonId = (int) $user->saloon_id;
        $validated = $request->validate([
            'scheme_id' => [
                'sometimes',
                'integer',
                Rule::exists('commission_schemes', 'id')->where(fn ($q) => $q->where('saloon_id', $saloonId)),
            ],
            'branch_id' => [
                'nullable',
                'integer',
                Rule::exists('saloon_branches', 'id')->where(fn ($q) => $q->where('saloon_id', $saloonId)),
            ],
            'effective_from' => ['nullable', 'date'],
            'effective_to' => ['nullable', 'date', 'after_or_equal:effective_from'],
            'override_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
        ]);

        if (array_key_exists('branch_id', $validated)) {
            $validated['branch_id'] = $this->resolveWritableBranch($user, $validated);
        }

        $assignment->update($validated);

        return response()->json([
            'message' => 'Staff commission assignment updated successfully.',
            'data' => [
                'assignment' => (new StaffCommissionAssignmentResource(
                    $assignment->fresh()->load(['user', 'scheme', 'branch'])
                ))->resolve(),
            ],
        ]);
    }

    public function destroy(Request $request, StaffCommissionAssignment $assignment): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        EnsuresPermission::one($user, 'commissions.manage');
        TenantScope::ensureSaloonAccess($user, (int) $assignment->saloon_id);
        BranchScope::ensureSameBranch(
            $user,
            $assignment->branch_id !== null ? (int) $assignment->branch_id : null,
            'You can only manage assignments for your current branch.',
        );

        $assignment->delete();

        return response()->json(['message' => 'Staff commission assignment removed successfully.']);
    }

    private function resolveBranchFilter(User $user, Request $request): ?int
    {
        if ($user->isBranchScopedActor() && $user->branch_id) {
            return (int) $user->branch_id;
        }

        return $request->filled('branch_id') ? (int) $request->integer('branch_id') : null;
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    private function resolveWritableBranch(User $user, array $validated): ?int
    {
        if ($user->isBranchScopedActor() && $user->branch_id) {
            return (int) $user->branch_id;
        }

        return isset($validated['branch_id']) && $validated['branch_id'] !== null
            ? (int) $validated['branch_id']
            : null;
    }
}
