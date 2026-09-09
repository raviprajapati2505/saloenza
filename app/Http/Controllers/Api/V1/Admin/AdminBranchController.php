<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Actions\Branch\ProvisionBranchAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\StoreAdminBranchRequest;
use App\Http\Requests\Api\V1\Admin\UpdateAdminBranchRequest;
use App\Http\Resources\Api\V1\Branch\BranchResource;
use App\Models\Saloon;
use App\Models\SaloonBranch;
use App\Support\Api\ListQuery;
use App\Support\Subscription\SubscriptionEntitlements;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminBranchController extends Controller
{
    public function __construct(
        private readonly ProvisionBranchAction $provisioner,
        private readonly SubscriptionEntitlements $entitlements,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $validated = ListQuery::validate($request, [
            'saloon_id' => ['sometimes', 'integer', 'exists:saloons,id'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $query = SaloonBranch::query()
            ->with(['saloon', 'manager'])
            ->withCount(['users', 'catalogItems'])
            ->latest('id');

        if (! empty($validated['saloon_id'])) {
            $query->where('saloon_id', (int) $validated['saloon_id']);
        }

        if (array_key_exists('is_active', $validated)) {
            $query->where('is_active', (bool) $validated['is_active']);
        }

        if (! empty($validated['search'])) {
            $search = trim((string) $validated['search']);
            $query->where(function ($builder) use ($search): void {
                $builder->where('branch_name', 'like', "%{$search}%")
                    ->orWhere('city', 'like', "%{$search}%")
                    ->orWhere('state', 'like', "%{$search}%")
                    ->orWhere('area_pincode', 'like', "%{$search}%")
                    ->orWhereHas('saloon', function ($saloonQuery) use ($search): void {
                        $saloonQuery->where('name', 'like', "%{$search}%");
                    });
            });
        }

        $paginator = ListQuery::paginate($query, $validated);

        $payload = ListQuery::responsePayload(
            'Platform branches fetched successfully.',
            'branches',
            $paginator,
            BranchResource::class,
        );

        if (! empty($validated['saloon_id'])) {
            $saloon = Saloon::query()->find((int) $validated['saloon_id']);
            if ($saloon !== null) {
                $snapshot = $this->entitlements->snapshot($saloon);
                $payload['data']['limits'] = $snapshot['limits'];
                $payload['data']['can_create_branch'] = $this->canCreateBranch($snapshot['limits']);
                $payload['data']['plan'] = $snapshot['plan'] ? [
                    'id' => $snapshot['plan']->id,
                    'name' => $snapshot['plan']->name,
                    'slug' => $snapshot['plan']->slug,
                ] : null;
            }
        }

        return response()->json($payload);
    }

    public function store(StoreAdminBranchRequest $request): JsonResponse
    {
        $payload = $request->validated();
        $saloon = Saloon::query()->findOrFail((int) $payload['saloon_id']);

        $result = $this->provisioner->create($saloon, $payload);
        $branch = $result['branch']->load(['saloon', 'manager'])->loadCount(['users', 'catalogItems']);

        return response()->json([
            'message' => 'Branch created successfully on behalf of salon.',
            'data' => [
                'branch' => (new BranchResource($branch))->resolve(),
                'manager' => $result['manager'],
                'cloned_catalog_items' => $result['cloned_catalog_items'],
                'cloned_roles' => $result['cloned_roles'],
            ],
        ], 201);
    }

    public function update(UpdateAdminBranchRequest $request, SaloonBranch $branch): JsonResponse
    {
        $branch = $this->provisioner->update($branch, $request->validated())
            ->load(['saloon', 'manager'])
            ->loadCount(['users', 'catalogItems']);

        return response()->json([
            'message' => 'Branch updated successfully.',
            'data' => [
                'branch' => (new BranchResource($branch))->resolve(),
            ],
        ]);
    }

    public function salonCapacity(Saloon $saloon): JsonResponse
    {
        $snapshot = $this->entitlements->snapshot($saloon);
        $limits = $snapshot['limits'];

        return response()->json([
            'message' => 'Salon branch capacity fetched successfully.',
            'data' => [
                'saloon_id' => $saloon->id,
                'saloon_name' => $saloon->name,
                'plan' => $snapshot['plan'] ? [
                    'id' => $snapshot['plan']->id,
                    'name' => $snapshot['plan']->name,
                    'slug' => $snapshot['plan']->slug,
                ] : null,
                'limits' => $limits,
                'can_create_branch' => $this->canCreateBranch($limits),
            ],
        ]);
    }

    /**
     * @param array{max_branches: int|null, max_staff: int|null, branches_used: int, staff_used: int} $limits
     */
    private function canCreateBranch(array $limits): bool
    {
        if ($limits['max_branches'] === null) {
            return true;
        }

        return $limits['branches_used'] < $limits['max_branches'];
    }
}
