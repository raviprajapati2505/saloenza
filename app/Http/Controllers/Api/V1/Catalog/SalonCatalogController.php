<?php

namespace App\Http\Controllers\Api\V1\Catalog;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Catalog\StoreSalonCatalogRequest;
use App\Http\Requests\Api\V1\Catalog\UpdateSalonCatalogRequest;
use App\Http\Resources\Api\V1\Catalog\SalonCatalogResource;
use App\Http\Resources\Api\V1\Common\MessageResponseResource;
use App\Models\SalonServiceProduct;
use App\Models\User;
use App\Support\Api\ListQuery;
use App\Support\Branch\BranchScope;
use App\Support\EnsuresPermission;
use App\Support\Tenant\TenantScope;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SalonCatalogController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        EnsuresPermission::any($user, ['services.view', 'products.view']);
        BranchScope::ensureAssigned($user);

        $saloonId = TenantScope::resolveSaloonFilter(
            $user,
            $request->filled('saloon_id') ? (int) $request->integer('saloon_id') : null,
        );

        $validated = ListQuery::validate($request, [
            'is_active' => ['sometimes', 'boolean'],
            'branch_id' => ['sometimes', 'nullable', 'integer', 'exists:saloon_branches,id'],
        ]);

        $query = SalonServiceProduct::query()
            ->with(['branch', 'service.category', 'product.category'])
            ->latest('id');

        if ($saloonId !== null) {
            $query->where('saloon_id', $saloonId);
        }

        if (array_key_exists('is_active', $validated)) {
            $query->where('is_active', (bool) $validated['is_active']);
        }

        if ($user->isBranchScopedActor()) {
            $query->where('branch_id', $user->branch_id);
        } elseif (array_key_exists('branch_id', $validated) && $validated['branch_id'] !== null) {
            $query->where('branch_id', (int) $validated['branch_id']);
        }

        $paginator = ListQuery::paginate($query, $validated);

        return response()->json(ListQuery::responsePayload(
            'Catalog items fetched successfully.',
            'catalog',
            $paginator,
            SalonCatalogResource::class,
        ));
    }

    public function store(StoreSalonCatalogRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        BranchScope::ensureAssigned($user);

        $productId = $request->validated('product_id');

        $item = SalonServiceProduct::query()->create([
            'saloon_id' => TenantScope::resolveSaloonId($user, $request->validated('saloon_id')),
            'branch_id' => BranchScope::resolveBranchId($user, $request->validated('branch_id')),
            'service_id' => (int) $request->validated('service_id'),
            'product_id' => $productId !== null ? (int) $productId : null,
            'price' => $request->validated('price'),
            'duration_minutes' => (int) $request->validated('duration_minutes'),
            'is_active' => (bool) $request->validated('is_active'),
        ]);

        $item->load(['branch', 'service.category', 'product.category']);

        return response()->json([
            'message' => 'Catalog item created successfully.',
            'data' => [
                'catalog' => (new SalonCatalogResource($item))->resolve(),
            ],
        ], 201);
    }

    public function show(Request $request, SalonServiceProduct $catalog): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        EnsuresPermission::any($user, ['services.view', 'products.view']);
        TenantScope::ensureSaloonAccess($user, (int) $catalog->saloon_id);
        BranchScope::ensureSameBranch(
            $user,
            $catalog->branch_id !== null ? (int) $catalog->branch_id : null,
            'You can only manage catalog items for your current branch.',
        );

        $catalog->load(['branch', 'service.category', 'product.category']);

        return response()->json([
            'message' => 'Catalog item fetched successfully.',
            'data' => [
                'catalog' => (new SalonCatalogResource($catalog))->resolve(),
            ],
        ]);
    }

    public function update(UpdateSalonCatalogRequest $request, SalonServiceProduct $catalog): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        TenantScope::ensureSaloonAccess($user, (int) $catalog->saloon_id);
        BranchScope::ensureSameBranch(
            $user,
            $catalog->branch_id !== null ? (int) $catalog->branch_id : null,
            'You can only manage catalog items for your current branch.',
        );

        $productId = $request->validated('product_id');

        $catalog->update([
            'branch_id' => $request->exists('branch_id')
                ? BranchScope::resolveBranchId($user, $request->validated('branch_id'))
                : $catalog->branch_id,
            'service_id' => (int) $request->validated('service_id'),
            'product_id' => $productId !== null ? (int) $productId : null,
            'price' => $request->validated('price'),
            'duration_minutes' => (int) $request->validated('duration_minutes'),
            'is_active' => (bool) $request->validated('is_active'),
        ]);

        $catalog->load(['branch', 'service.category', 'product.category']);

        return response()->json([
            'message' => 'Catalog item updated successfully.',
            'data' => [
                'catalog' => (new SalonCatalogResource($catalog->fresh()))->resolve(),
            ],
        ]);
    }

    public function destroy(Request $request, SalonServiceProduct $catalog): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        EnsuresPermission::any($user, ['services.delete', 'products.delete']);
        TenantScope::ensureSaloonAccess($user, (int) $catalog->saloon_id);
        BranchScope::ensureSameBranch(
            $user,
            $catalog->branch_id !== null ? (int) $catalog->branch_id : null,
            'You can only manage catalog items for your current branch.',
        );

        $catalog->delete();

        return (new MessageResponseResource([
            'message' => 'Catalog item deleted successfully.',
        ]))->response();
    }
}
