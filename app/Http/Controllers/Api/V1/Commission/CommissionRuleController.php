<?php

namespace App\Http\Controllers\Api\V1\Commission;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\Commission\CommissionRuleResource;
use App\Models\CommissionRule;
use App\Models\CommissionScheme;
use App\Models\User;
use App\Support\Api\ListQuery;
use App\Support\EnsuresPermission;
use App\Support\Tenant\TenantScope;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CommissionRuleController extends Controller
{
    public function index(Request $request, CommissionScheme $scheme): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        EnsuresPermission::one($user, 'commissions.view');
        TenantScope::ensureSaloonAccess($user, (int) $scheme->saloon_id);

        $validated = ListQuery::validate($request);

        $query = CommissionRule::query()
            ->where('scheme_id', $scheme->id)
            ->with(['service', 'product', 'category', 'staffUser'])
            ->orderByDesc('priority')
            ->orderBy('id');

        ListQuery::applySearch($query, $validated['search'] ?? null, ['name']);
        $paginator = ListQuery::paginate($query, $validated);

        return response()->json(ListQuery::responsePayload(
            'Commission rules fetched successfully.',
            'rules',
            $paginator,
            CommissionRuleResource::class,
        ));
    }

    public function store(Request $request, CommissionScheme $scheme): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        EnsuresPermission::one($user, 'commissions.manage');
        TenantScope::ensureSaloonAccess($user, (int) $scheme->saloon_id);

        $validated = $request->validate($this->rules($user));

        $rule = CommissionRule::query()->create([
            ...$validated,
            'scheme_id' => $scheme->id,
        ]);

        return response()->json([
            'message' => 'Commission rule created successfully.',
            'data' => [
                'rule' => (new CommissionRuleResource(
                    $rule->load(['service', 'product', 'category', 'staffUser'])
                ))->resolve(),
            ],
        ], 201);
    }

    public function update(Request $request, CommissionScheme $scheme, CommissionRule $rule): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        EnsuresPermission::one($user, 'commissions.manage');
        TenantScope::ensureSaloonAccess($user, (int) $scheme->saloon_id);
        $this->ensureRuleBelongsToScheme($scheme, $rule);

        $validated = $request->validate($this->rules($user, partial: true));
        $rule->update($validated);

        return response()->json([
            'message' => 'Commission rule updated successfully.',
            'data' => [
                'rule' => (new CommissionRuleResource(
                    $rule->fresh()->load(['service', 'product', 'category', 'staffUser'])
                ))->resolve(),
            ],
        ]);
    }

    public function destroy(Request $request, CommissionScheme $scheme, CommissionRule $rule): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        EnsuresPermission::one($user, 'commissions.manage');
        TenantScope::ensureSaloonAccess($user, (int) $scheme->saloon_id);
        $this->ensureRuleBelongsToScheme($scheme, $rule);

        $rule->update(['is_active' => false]);

        return response()->json([
            'message' => 'Commission rule deactivated successfully.',
            'data' => [
                'rule' => (new CommissionRuleResource($rule->fresh()))->resolve(),
            ],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function rules(User $user, bool $partial = false): array
    {
        $required = $partial ? 'sometimes' : 'required';
        $saloonId = (int) $user->saloon_id;

        return [
            'priority' => ['sometimes', 'integer', 'min:0', 'max:10000'],
            'name' => [$required, 'string', 'max:120'],
            'applies_to' => [$required, 'string', Rule::in(['service', 'product', 'all'])],
            'service_id' => ['nullable', 'integer', 'exists:services,id'],
            'category_id' => ['nullable', 'integer', 'exists:categories,id'],
            'product_id' => ['nullable', 'integer', 'exists:products,id'],
            'staff_user_id' => [
                'nullable',
                'integer',
                Rule::exists('users', 'id')->where(
                    fn ($q) => $q->where('saloon_id', $saloonId),
                ),
            ],
            'role_id' => ['nullable', 'integer', 'exists:roles,id'],
            'calc_type' => [
                $required,
                'string',
                Rule::in([
                    'percent_of_revenue',
                    'percent_of_net',
                    'fixed_per_line',
                    'fixed_per_minute',
                    'tiered_percent',
                ]),
            ],
            'rate_value' => [$required, 'numeric', 'min:0', 'max:99999999'],
            'tier_json' => ['nullable', 'array'],
            'tier_json.*.min' => ['required_with:tier_json', 'numeric', 'min:0'],
            'tier_json.*.max' => ['nullable', 'numeric', 'min:0'],
            'tier_json.*.rate' => ['required_with:tier_json', 'numeric', 'min:0'],
            'include_discounts' => ['sometimes', 'boolean'],
            'min_line_price' => ['nullable', 'numeric', 'min:0'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    private function ensureRuleBelongsToScheme(CommissionScheme $scheme, CommissionRule $rule): void
    {
        if ((int) $rule->scheme_id !== (int) $scheme->id) {
            abort(404, 'Commission rule not found for this scheme.');
        }
    }
}
