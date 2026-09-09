<?php

namespace App\Http\Controllers\Api\V1\Expense;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\Expense\ExpenseResource;
use App\Models\Expense;
use App\Models\User;
use App\Support\Api\ListQuery;
use App\Support\Branch\BranchScope;
use App\Support\EnsuresPermission;
use App\Support\Expense\ExpenseCategory;
use App\Support\Tenant\TenantScope;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ExpenseController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        EnsuresPermission::one($user, 'expenses.view');
        $saloonId = TenantScope::resolveSaloonFilter($user, null);

        $validated = ListQuery::validate($request, [
            'from' => ['sometimes', 'date'],
            'to' => ['sometimes', 'date'],
            'branch_id' => ['sometimes', 'integer', 'exists:saloon_branches,id'],
            'category' => ['sometimes', 'string', Rule::in(ExpenseCategory::all())],
        ]);

        $branchId = $this->resolveBranchFilter($user, $request);

        $query = Expense::query()
            ->with(['branch', 'creator'])
            ->when($saloonId !== null, fn ($q) => $q->where('saloon_id', $saloonId))
            ->forBranch($branchId)
            ->orderByDesc('incurred_on')
            ->orderByDesc('id');

        if (array_key_exists('from', $validated)) {
            $query->whereDate('incurred_on', '>=', $validated['from']);
        }

        if (array_key_exists('to', $validated)) {
            $query->whereDate('incurred_on', '<=', $validated['to']);
        }

        if (array_key_exists('category', $validated)) {
            $query->where('category', $validated['category']);
        }

        ListQuery::applySearch($query, $validated['search'] ?? null, ['title', 'notes']);
        $paginator = ListQuery::paginate($query, $validated);

        return response()->json(ListQuery::responsePayload(
            'Expenses fetched successfully.',
            'expenses',
            $paginator,
            ExpenseResource::class,
        ));
    }

    public function categories(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        EnsuresPermission::one($user, 'expenses.view');

        return response()->json([
            'message' => 'Expense categories fetched successfully.',
            'data' => [
                'categories' => array_map(
                    static fn (string $key): array => [
                        'value' => $key,
                        'label' => ExpenseCategory::label($key),
                    ],
                    ExpenseCategory::all(),
                ),
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        EnsuresPermission::one($user, 'expenses.manage');

        $validated = $request->validate($this->rules($user));
        $branchId = $this->resolveWritableBranch($user, $validated);

        $expense = Expense::query()->create([
            ...$validated,
            'saloon_id' => (int) $user->saloon_id,
            'branch_id' => $branchId,
            'created_by' => (int) $user->id,
        ]);

        return response()->json([
            'message' => 'Expense recorded successfully.',
            'data' => ['expense' => (new ExpenseResource($expense->load(['branch', 'creator'])))->resolve()],
        ], 201);
    }

    public function update(Request $request, Expense $expense): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        EnsuresPermission::one($user, 'expenses.manage');
        TenantScope::ensureSaloonAccess($user, (int) $expense->saloon_id);
        $this->ensureBranchAccess($user, $expense);

        $validated = $request->validate($this->rules($user, partial: true));

        if (array_key_exists('branch_id', $validated)) {
            $validated['branch_id'] = $this->resolveWritableBranch($user, $validated);
        }

        $expense->update($validated);

        return response()->json([
            'message' => 'Expense updated successfully.',
            'data' => ['expense' => (new ExpenseResource($expense->fresh(['branch', 'creator'])))->resolve()],
        ]);
    }

    public function destroy(Request $request, Expense $expense): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        EnsuresPermission::one($user, 'expenses.manage');
        TenantScope::ensureSaloonAccess($user, (int) $expense->saloon_id);
        $this->ensureBranchAccess($user, $expense);

        $expense->delete();

        return response()->json(['message' => 'Expense deleted successfully.']);
    }

    /**
     * @return array<string, mixed>
     */
    private function rules(User $user, bool $partial = false): array
    {
        $required = $partial ? 'sometimes' : 'required';

        return [
            'category' => [$required, 'string', Rule::in(ExpenseCategory::all())],
            'title' => [$required, 'string', 'max:160'],
            'amount' => [$required, 'numeric', 'min:0.01', 'max:99999999'],
            'incurred_on' => [$required, 'date'],
            'branch_id' => [
                'nullable',
                'integer',
                Rule::exists('saloon_branches', 'id')->where(
                    fn ($query) => $query->where('saloon_id', (int) $user->saloon_id),
                ),
            ],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
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

    private function ensureBranchAccess(User $user, Expense $expense): void
    {
        BranchScope::ensureSameBranch(
            $user,
            $expense->branch_id !== null ? (int) $expense->branch_id : null,
            'You can only manage expenses for your current branch.',
        );
    }
}
