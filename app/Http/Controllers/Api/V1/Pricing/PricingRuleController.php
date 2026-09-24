<?php

namespace App\Http\Controllers\Api\V1\Pricing;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\Pricing\PricingRuleResource;
use App\Models\PricingRule;
use App\Models\User;
use App\Services\Pricing\PricingRuleService;
use App\Support\Api\ListQuery;
use App\Support\Branch\BranchScope;
use App\Support\EnsuresPermission;
use App\Support\Pricing\PricingAdjustmentType;
use App\Support\Tenant\TenantScope;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PricingRuleController extends Controller
{
    public function __construct(
        private readonly PricingRuleService $pricing,
    ) {}

    public function index(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        EnsuresPermission::one($user, 'pricing_rules.view');

        $saloonId = TenantScope::resolveSaloonId($user, null);
        $validated = ListQuery::validate($request, [
            'branch_id' => ['sometimes', 'integer', 'exists:saloon_branches,id'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $branchId = $user->isBranchScopedActor() && $user->branch_id
            ? (int) $user->branch_id
            : ($request->filled('branch_id') ? (int) $request->integer('branch_id') : null);

        $query = PricingRule::query()
            ->with(['branch:id,branch_name'])
            ->where('saloon_id', $saloonId)
            ->when($branchId !== null, function ($q) use ($branchId): void {
                $q->where(function ($inner) use ($branchId): void {
                    $inner->whereNull('branch_id')->orWhere('branch_id', $branchId);
                });
            })
            ->when(array_key_exists('is_active', $validated), fn ($q) => $q->where('is_active', (bool) $validated['is_active']))
            ->orderByDesc('priority')
            ->orderByDesc('id');

        ListQuery::applySearch($query, $validated['search'] ?? null, ['name']);
        $paginator = ListQuery::paginate($query, $validated);

        return response()->json(ListQuery::responsePayload(
            'Pricing rules fetched successfully.',
            'pricing_rules',
            $paginator,
            PricingRuleResource::class,
        ));
    }

    public function store(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        EnsuresPermission::one($user, 'pricing_rules.manage');

        $saloonId = TenantScope::resolveSaloonId($user, null);
        $validated = $request->validate($this->rules($saloonId));
        $validated = $this->normalizePayload($validated);

        $branchId = $this->resolveWritableBranch($user, $validated);

        $rule = PricingRule::query()->create([
            ...$validated,
            'saloon_id' => $saloonId,
            'branch_id' => $branchId,
            'is_active' => (bool) ($validated['is_active'] ?? true),
            'priority' => (int) ($validated['priority'] ?? 0),
            'stackable' => (bool) ($validated['stackable'] ?? false),
            'channel' => $validated['channel'] ?? 'all',
        ]);

        return response()->json([
            'message' => 'Pricing rule created successfully.',
            'data' => ['pricing_rule' => (new PricingRuleResource($rule->load('branch')))->resolve()],
        ], 201);
    }

    public function update(Request $request, PricingRule $pricingRule): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        EnsuresPermission::one($user, 'pricing_rules.manage');
        TenantScope::ensureSaloonAccess($user, (int) $pricingRule->saloon_id);
        BranchScope::ensureSameBranch(
            $user,
            $pricingRule->branch_id !== null ? (int) $pricingRule->branch_id : null,
        );

        $validated = $request->validate($this->rules((int) $pricingRule->saloon_id, partial: true));
        $validated = $this->normalizePayload($validated);

        if (array_key_exists('branch_id', $validated)) {
            $validated['branch_id'] = $this->resolveWritableBranch($user, $validated);
        }

        $pricingRule->update($validated);

        return response()->json([
            'message' => 'Pricing rule updated successfully.',
            'data' => ['pricing_rule' => (new PricingRuleResource($pricingRule->fresh('branch')))->resolve()],
        ]);
    }

    public function destroy(Request $request, PricingRule $pricingRule): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        EnsuresPermission::one($user, 'pricing_rules.manage');
        TenantScope::ensureSaloonAccess($user, (int) $pricingRule->saloon_id);
        BranchScope::ensureSameBranch(
            $user,
            $pricingRule->branch_id !== null ? (int) $pricingRule->branch_id : null,
        );

        $pricingRule->delete();

        return response()->json(['message' => 'Pricing rule deleted successfully.']);
    }

    public function resolve(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        EnsuresPermission::any($user, ['pricing_rules.view', 'appointments.create', 'appointments.view']);

        $saloonId = TenantScope::resolveSaloonId($user, null);

        $validated = $request->validate([
            'service_id' => ['required', 'integer', 'exists:services,id'],
            'branch_id' => ['nullable', 'integer', 'exists:saloon_branches,id'],
            'staff_id' => ['nullable', 'integer', 'exists:users,id'],
            'datetime' => ['nullable', 'date'],
            'channel' => ['nullable', 'string', 'max:40'],
            'list_price' => ['nullable', 'numeric', 'min:0'],
        ]);

        $result = $this->pricing->resolvePrice(
            $saloonId,
            isset($validated['branch_id']) ? (int) $validated['branch_id'] : null,
            (int) $validated['service_id'],
            $validated['datetime'] ?? null,
            (string) ($validated['channel'] ?? 'internal'),
            isset($validated['staff_id']) ? (int) $validated['staff_id'] : null,
            isset($validated['list_price']) ? (float) $validated['list_price'] : null,
        );

        return response()->json([
            'message' => 'Price resolved successfully.',
            'data' => [
                'list_price' => $result['list_price'],
                'final_price' => $result['final_price'],
                'adjustment_amount' => $result['adjustment_amount'],
                'pricing_rule_id' => $result['pricing_rule_id'],
                'pricing_rule' => $result['pricing_rule']
                    ? (new PricingRuleResource($result['pricing_rule']))->resolve()
                    : null,
            ],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function rules(int $saloonId, bool $partial = false): array
    {
        $required = $partial ? 'sometimes' : 'required';

        return [
            'name' => [$required, 'string', 'max:160'],
            'is_active' => ['sometimes', 'boolean'],
            'priority' => ['sometimes', 'integer', 'min:0', 'max:10000'],
            'adjustment_type' => [$required, 'string', Rule::in(PricingAdjustmentType::all())],
            'adjustment_value' => [$required, 'numeric', 'min:0', 'max:99999999'],
            'branch_id' => [
                'nullable',
                'integer',
                Rule::exists('saloon_branches', 'id')->where(
                    fn ($query) => $query->where('saloon_id', $saloonId),
                ),
            ],
            'service_ids' => ['nullable', 'array'],
            'service_ids.*' => ['integer', 'exists:services,id'],
            'category_ids' => ['nullable', 'array'],
            'category_ids.*' => ['integer', 'exists:categories,id'],
            'staff_ids' => ['nullable', 'array'],
            'staff_ids.*' => ['integer', 'exists:users,id'],
            'days_of_week' => ['nullable', 'array'],
            'days_of_week.*' => ['integer', 'min:0', 'max:6'],
            'time_start' => ['nullable', 'string', 'regex:/^\d{2}:\d{2}(:\d{2})?$/'],
            'time_end' => ['nullable', 'string', 'regex:/^\d{2}:\d{2}(:\d{2})?$/'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
            'min_lead_hours' => ['nullable', 'integer', 'min:0', 'max:720'],
            'channel' => ['sometimes', 'string', Rule::in(['all', 'self_booking', 'internal'])],
            'stackable' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    private function normalizePayload(array $validated): array
    {
        foreach (['time_start', 'time_end', 'date_from', 'date_to'] as $field) {
            if (array_key_exists($field, $validated) && $validated[$field] === '') {
                $validated[$field] = null;
            }
        }

        return $validated;
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
