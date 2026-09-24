<?php

namespace App\Http\Controllers\Api\V1\Commission;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\Commission\CommissionSchemeResource;
use App\Models\CommissionScheme;
use App\Models\User;
use App\Services\Commission\CommissionRuleResolver;
use App\Support\Api\ListQuery;
use App\Support\EnsuresPermission;
use App\Support\Tenant\TenantScope;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class CommissionSchemeController extends Controller
{
    public function __construct(
        private readonly CommissionRuleResolver $resolver,
    ) {}

    public function index(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        EnsuresPermission::one($user, 'commissions.view');
        $saloonId = TenantScope::resolveSaloonFilter($user, null);

        $validated = ListQuery::validate($request, [
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $query = CommissionScheme::query()
            ->withCount('rules')
            ->when($saloonId !== null, fn ($q) => $q->where('saloon_id', $saloonId))
            ->orderByDesc('is_default')
            ->orderBy('name');

        if (array_key_exists('is_active', $validated)) {
            $query->where('is_active', (bool) $validated['is_active']);
        }

        ListQuery::applySearch($query, $validated['search'] ?? null, ['name']);
        $paginator = ListQuery::paginate($query, $validated);

        return response()->json(ListQuery::responsePayload(
            'Commission schemes fetched successfully.',
            'schemes',
            $paginator,
            CommissionSchemeResource::class,
        ));
    }

    public function store(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        EnsuresPermission::one($user, 'commissions.manage');

        $saloonId = (int) $user->saloon_id;

        if ($request->boolean('migrate_from_flat_rates')) {
            $scheme = $this->resolver->migrateFromFlatRates($saloonId);

            if ($scheme === null) {
                return response()->json([
                    'message' => 'Commission tables are not ready. Run migrations first.',
                ], 503);
            }

            return response()->json([
                'message' => 'Default commission scheme created from staff flat rates.',
                'data' => [
                    'scheme' => (new CommissionSchemeResource(
                        $scheme->load('rules')->loadCount('rules')
                    ))->resolve(),
                ],
            ], 201);
        }

        $validated = $request->validate($this->rules());

        $scheme = DB::transaction(function () use ($validated, $saloonId): CommissionScheme {
            if (! empty($validated['is_default'])) {
                CommissionScheme::query()
                    ->where('saloon_id', $saloonId)
                    ->update(['is_default' => false]);
            }

            return CommissionScheme::query()->create([
                'saloon_id' => $saloonId,
                'name' => $validated['name'],
                'is_default' => (bool) ($validated['is_default'] ?? false),
                'is_active' => (bool) ($validated['is_active'] ?? true),
                'currency' => $validated['currency'] ?? null,
                'effective_from' => $validated['effective_from'] ?? null,
                'effective_to' => $validated['effective_to'] ?? null,
                'commission_on_no_show' => (bool) ($validated['commission_on_no_show'] ?? false),
                'commission_when_payment_unpaid' => $validated['commission_when_payment_unpaid'] ?? 'on_complete',
            ]);
        });

        return response()->json([
            'message' => 'Commission scheme created successfully.',
            'data' => [
                'scheme' => (new CommissionSchemeResource($scheme->loadCount('rules')))->resolve(),
            ],
        ], 201);
    }

    public function show(Request $request, CommissionScheme $scheme): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        EnsuresPermission::one($user, 'commissions.view');
        TenantScope::ensureSaloonAccess($user, (int) $scheme->saloon_id);

        $scheme->load(['rules' => fn ($q) => $q->orderByDesc('priority')->orderBy('id')])
            ->loadCount('rules');

        return response()->json([
            'message' => 'Commission scheme fetched successfully.',
            'data' => [
                'scheme' => (new CommissionSchemeResource($scheme))->resolve(),
            ],
        ]);
    }

    public function update(Request $request, CommissionScheme $scheme): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        EnsuresPermission::one($user, 'commissions.manage');
        TenantScope::ensureSaloonAccess($user, (int) $scheme->saloon_id);

        $validated = $request->validate($this->rules(partial: true));

        DB::transaction(function () use ($validated, $scheme): void {
            if (! empty($validated['is_default'])) {
                CommissionScheme::query()
                    ->where('saloon_id', $scheme->saloon_id)
                    ->where('id', '!=', $scheme->id)
                    ->update(['is_default' => false]);
            }

            $scheme->update($validated);
        });

        return response()->json([
            'message' => 'Commission scheme updated successfully.',
            'data' => [
                'scheme' => (new CommissionSchemeResource(
                    $scheme->fresh()->load(['rules'])->loadCount('rules')
                ))->resolve(),
            ],
        ]);
    }

    public function destroy(Request $request, CommissionScheme $scheme): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        EnsuresPermission::one($user, 'commissions.manage');
        TenantScope::ensureSaloonAccess($user, (int) $scheme->saloon_id);

        $scheme->update(['is_active' => false]);

        return response()->json([
            'message' => 'Commission scheme deactivated successfully.',
            'data' => [
                'scheme' => (new CommissionSchemeResource($scheme->fresh()->loadCount('rules')))->resolve(),
            ],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function rules(bool $partial = false): array
    {
        $required = $partial ? 'sometimes' : 'required';

        return [
            'name' => [$required, 'string', 'max:120'],
            'is_default' => ['sometimes', 'boolean'],
            'is_active' => ['sometimes', 'boolean'],
            'currency' => ['nullable', 'string', 'max:10'],
            'effective_from' => ['nullable', 'date'],
            'effective_to' => ['nullable', 'date', 'after_or_equal:effective_from'],
            'commission_on_no_show' => ['sometimes', 'boolean'],
            'commission_when_payment_unpaid' => [
                'sometimes',
                'string',
                Rule::in(['on_complete', 'when_paid']),
            ],
        ];
    }
}
