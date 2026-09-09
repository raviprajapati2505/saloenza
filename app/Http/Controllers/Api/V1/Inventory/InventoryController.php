<?php

namespace App\Http\Controllers\Api\V1\Inventory;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\Inventory\InventoryStockResource;
use App\Http\Resources\Api\V1\Inventory\PurchaseOrderResource;
use App\Http\Resources\Api\V1\Inventory\SupplierResource;
use App\Models\BranchProductStock;
use App\Models\PurchaseOrder;
use App\Models\Supplier;
use App\Models\User;
use App\Services\Inventory\BranchStockService;
use App\Services\Inventory\InventoryQueryService;
use App\Services\Inventory\PurchaseOrderService;
use App\Support\Api\ListQuery;
use App\Support\Branch\BranchScope;
use App\Support\EnsuresPermission;
use App\Support\Tenant\TenantScope;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class InventoryController extends Controller
{
    public function __construct(
        private readonly InventoryQueryService $inventory,
        private readonly BranchStockService $stock,
    ) {
    }

    public function summary(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        EnsuresPermission::one($user, 'inventory.view');

        $branchId = $this->resolveBranchId($user, $request);

        return response()->json([
            'message' => 'Inventory summary fetched successfully.',
            'data' => ['summary' => $this->inventory->summary($branchId)],
        ]);
    }

    public function stock(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        EnsuresPermission::one($user, 'inventory.view');

        $branchId = $this->resolveBranchId($user, $request);
        $validated = ListQuery::validate($request, [
            'category_id' => ['sometimes', 'integer', 'exists:categories,id'],
            'low_stock' => ['sometimes', 'boolean'],
            'out_of_stock' => ['sometimes', 'boolean'],
        ]);

        $query = $this->inventory->stockQuery($branchId, $validated);
        $paginator = ListQuery::paginate($query, $validated);

        return response()->json(ListQuery::responsePayload(
            'Inventory stock fetched successfully.',
            'stock',
            $paginator,
            InventoryStockResource::class,
        ));
    }

    public function storeStock(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        EnsuresPermission::one($user, 'inventory.manage');

        $validated = $request->validate([
            'branch_id' => ['required', 'integer', 'exists:saloon_branches,id'],
            'product_id' => ['required', 'integer', 'exists:products,id'],
            'quantity_on_hand' => ['required', 'integer', 'min:0'],
            'reorder_level' => ['sometimes', 'integer', 'min:0'],
            'max_stock' => ['sometimes', 'integer', 'min:1'],
            'cost_price' => ['nullable', 'numeric', 'min:0'],
            'selling_price' => ['nullable', 'numeric', 'min:0'],
            'supplier_id' => ['nullable', 'integer', 'exists:suppliers,id'],
        ]);

        $branchId = (int) $validated['branch_id'];
        if ($user->isBranchScopedActor()) {
            BranchScope::ensureSameBranch(
                $user,
                $branchId,
                'You can only manage inventory for your current branch.',
            );
        }

        $row = $this->stock->upsert(
            $branchId,
            (int) $validated['product_id'],
            (int) $validated['quantity_on_hand'],
            (int) ($validated['reorder_level'] ?? 5),
            array_filter([
                'max_stock' => $validated['max_stock'] ?? 100,
                'cost_price' => $validated['cost_price'] ?? null,
                'selling_price' => $validated['selling_price'] ?? null,
                'supplier_id' => $validated['supplier_id'] ?? null,
            ], fn ($v) => $v !== null),
        );

        return response()->json([
            'message' => 'Stock record saved successfully.',
            'data' => ['stock' => (new InventoryStockResource($row->load(['product.category', 'supplier'])))->resolve()],
        ], 201);
    }

    public function adjust(Request $request, BranchProductStock $stock): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        EnsuresPermission::one($user, 'inventory.manage');
        if ($user->isBranchScopedActor()) {
            BranchScope::ensureSameBranch(
                $user,
                (int) $stock->branch_id,
                'You can only adjust stock for your current branch.',
            );
        }

        $validated = $request->validate([
            'quantity_change' => ['required', 'integer', 'not_in:0'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        $branch = $stock->branch;
        $saloonId = (int) ($branch?->saloon_id ?? $user->saloon_id);

        $updated = $this->stock->adjust(
            $saloonId,
            (int) $stock->branch_id,
            (int) $stock->product_id,
            (int) $validated['quantity_change'],
            (int) $user->id,
            $validated['notes'] ?? null,
        );

        return response()->json([
            'message' => 'Stock adjusted successfully.',
            'data' => ['stock' => (new InventoryStockResource($updated))->resolve()],
        ]);
    }

    public function movements(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        EnsuresPermission::one($user, 'inventory.view');
        $saloonId = TenantScope::resolveSaloonFilter($user, null);

        $validated = ListQuery::validate($request, [
            'branch_id' => ['sometimes', 'integer', 'exists:saloon_branches,id'],
            'product_id' => ['sometimes', 'integer', 'exists:products,id'],
        ]);

        if ($user->isBranchScopedActor() && $user->branch_id) {
            $validated['branch_id'] = (int) $user->branch_id;
        }

        $query = $this->inventory->movementQuery((int) $saloonId, $validated);
        $paginator = ListQuery::paginate($query, $validated);

        return response()->json([
            'message' => 'Inventory movements fetched successfully.',
            'data' => [
                'movements' => collect($paginator->items())->map(fn ($row) => [
                    'id' => $row->id,
                    'type' => $row->type,
                    'quantity_change' => $row->quantity_change,
                    'quantity_after' => $row->quantity_after,
                    'notes' => $row->notes,
                    'created_at' => $row->created_at?->toISOString(),
                    'product' => $row->product ? ['id' => $row->product->id, 'name' => $row->product->name] : null,
                    'creator' => $row->creator ? ['id' => $row->creator->id, 'name' => $row->creator->name] : null,
                ]),
                'meta' => [
                    'current_page' => $paginator->currentPage(),
                    'last_page' => $paginator->lastPage(),
                    'per_page' => $paginator->perPage(),
                    'total' => $paginator->total(),
                ],
            ],
        ]);
    }

    private function resolveBranchId(User $user, Request $request): int
    {
        $branchId = BranchScope::resolveBranchId($user, $request->input('branch_id'));

        if ($branchId === null || $branchId <= 0) {
            abort(422, 'branch_id is required.');
        }

        if ($user->isBranchScopedActor()) {
            BranchScope::ensureSameBranch(
                $user,
                $branchId,
                'You can only access inventory for your current branch.',
            );
        }

        return $branchId;
    }
}
