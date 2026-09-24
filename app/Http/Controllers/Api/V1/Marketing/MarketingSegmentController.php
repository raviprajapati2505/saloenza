<?php

namespace App\Http\Controllers\Api\V1\Marketing;

use App\Http\Controllers\Controller;
use App\Models\CustomerSegment;
use App\Models\User;
use App\Services\Marketing\MarketingSegmentService;
use App\Support\EnsuresPermission;
use App\Support\Tenant\TenantScope;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class MarketingSegmentController extends Controller
{
    public function __construct(
        private readonly MarketingSegmentService $segments,
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        EnsuresPermission::one($user, 'marketing.view');

        $saloonId = (int) TenantScope::resolveSaloonFilter($user, null);

        $rows = CustomerSegment::query()
            ->forSaloon($saloonId)
            ->orderByDesc('updated_at')
            ->get();

        return response()->json([
            'message' => 'Customer segments fetched successfully.',
            'data' => [
                'segments' => $rows->map(fn (CustomerSegment $segment) => $this->payload($segment))->all(),
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        EnsuresPermission::one($user, 'marketing.manage');

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'type' => ['required', Rule::in([CustomerSegment::TYPE_STATIC, CustomerSegment::TYPE_DYNAMIC])],
            'rules' => ['sometimes', 'array'],
            'rules_json' => ['sometimes', 'array'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $saloonId = (int) TenantScope::resolveSaloonFilter($user, null);
        $segment = $this->segments->create($saloonId, $validated);

        return response()->json([
            'message' => 'Customer segment created successfully.',
            'data' => ['segment' => $this->payload($segment)],
        ], 201);
    }

    public function update(Request $request, CustomerSegment $segment): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        EnsuresPermission::one($user, 'marketing.manage');
        $this->ensureSegmentAccess($user, $segment);

        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:120'],
            'type' => ['sometimes', Rule::in([CustomerSegment::TYPE_STATIC, CustomerSegment::TYPE_DYNAMIC])],
            'rules' => ['sometimes', 'array'],
            'rules_json' => ['sometimes', 'array'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $segment = $this->segments->update($segment, $validated);

        return response()->json([
            'message' => 'Customer segment updated successfully.',
            'data' => ['segment' => $this->payload($segment)],
        ]);
    }

    public function destroy(Request $request, CustomerSegment $segment): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        EnsuresPermission::one($user, 'marketing.manage');
        $this->ensureSegmentAccess($user, $segment);

        $segment->delete();

        return response()->json([
            'message' => 'Customer segment deleted successfully.',
            'data' => null,
        ]);
    }

    public function preview(Request $request, CustomerSegment $segment): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        EnsuresPermission::one($user, 'marketing.view');
        $this->ensureSegmentAccess($user, $segment);

        $customers = $this->segments->resolveCustomers($segment);
        $segment = $this->segments->refreshSize($segment);

        return response()->json([
            'message' => 'Segment preview fetched successfully.',
            'data' => [
                'segment' => $this->payload($segment),
                'customers' => $customers->take(50)->map(fn ($customer) => [
                    'id' => $customer->id,
                    'name' => $customer->name,
                    'email' => $customer->email,
                    'phone' => $customer->phone,
                ])->values()->all(),
                'total' => $customers->count(),
            ],
        ]);
    }

    private function ensureSegmentAccess(User $user, CustomerSegment $segment): void
    {
        $saloonId = TenantScope::resolveSaloonFilter($user, null);
        if ($saloonId !== null && (int) $segment->saloon_id !== (int) $saloonId) {
            abort(404);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(CustomerSegment $segment): array
    {
        return [
            'id' => $segment->id,
            'saloon_id' => $segment->saloon_id,
            'name' => $segment->name,
            'type' => $segment->type,
            'rules_json' => $segment->rules_json,
            'estimated_size' => (int) $segment->estimated_size,
            'is_active' => (bool) $segment->is_active,
            'created_at' => $segment->created_at?->toISOString(),
            'updated_at' => $segment->updated_at?->toISOString(),
        ];
    }
}
