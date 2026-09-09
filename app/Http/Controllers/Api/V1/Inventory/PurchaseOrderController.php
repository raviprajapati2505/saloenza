<?php

namespace App\Http\Controllers\Api\V1\Inventory;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\Inventory\PurchaseOrderResource;
use App\Models\PurchaseOrder;
use App\Models\User;
use App\Services\Inventory\PurchaseOrderService;
use App\Support\Api\ListQuery;
use App\Support\Branch\BranchScope;
use App\Support\EnsuresPermission;
use App\Support\Tenant\TenantScope;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PurchaseOrderController extends Controller
{
    public function __construct(
        private readonly PurchaseOrderService $orders,
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        EnsuresPermission::one($user, 'inventory.view');
        $saloonId = TenantScope::resolveSaloonFilter($user, null);

        $validated = ListQuery::validate($request, [
            'branch_id' => ['sometimes', 'integer', 'exists:saloon_branches,id'],
            'status' => ['sometimes', 'string', 'max:20'],
        ]);

        $query = PurchaseOrder::query()
            ->with(['supplier', 'branch', 'items.product'])
            ->when($saloonId !== null, fn ($q) => $q->where('saloon_id', $saloonId))
            ->latest('id');

        if ($user->isBranchScopedActor() && $user->branch_id) {
            $query->where('branch_id', $user->branch_id);
        } elseif (! empty($validated['branch_id'])) {
            $query->where('branch_id', (int) $validated['branch_id']);
        }

        if (! empty($validated['status'])) {
            $query->where('status', $validated['status']);
        }

        $paginator = ListQuery::paginate($query, $validated);

        return response()->json(ListQuery::responsePayload(
            'Purchase orders fetched successfully.',
            'purchase_orders',
            $paginator,
            PurchaseOrderResource::class,
        ));
    }

    public function store(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        EnsuresPermission::one($user, 'inventory.manage');
        if ($user->isBranchScopedActor()) {
            BranchScope::ensureSameBranch(
                $user,
                (int) $request->integer('branch_id'),
                'You can only create purchase orders for your current branch.',
            );
        }

        $validated = $request->validate([
            'branch_id' => ['required', 'integer', 'exists:saloon_branches,id'],
            'supplier_id' => ['nullable', 'integer', 'exists:suppliers,id'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'integer', 'exists:products,id'],
            'items.*.quantity_ordered' => ['required', 'integer', 'min:1'],
            'items.*.unit_cost' => ['nullable', 'numeric', 'min:0'],
            'place_order' => ['sometimes', 'boolean'],
            'receive_now' => ['sometimes', 'boolean'],
        ]);

        $order = $this->orders->create($user, $validated);

        if ($validated['place_order'] ?? false) {
            $order = $this->orders->placeOrder($order);
        }

        if ($validated['receive_now'] ?? false) {
            $order = $this->orders->receive($order, $user);
        }

        return response()->json([
            'message' => 'Purchase order created successfully.',
            'data' => ['purchase_order' => (new PurchaseOrderResource($order))->resolve()],
        ], 201);
    }

    public function show(Request $request, PurchaseOrder $purchaseOrder): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        EnsuresPermission::one($user, 'inventory.view');
        TenantScope::ensureSaloonAccess($user, (int) $purchaseOrder->saloon_id);
        if ($user->isBranchScopedActor()) {
            BranchScope::ensureSameBranch(
                $user,
                (int) $purchaseOrder->branch_id,
                'You can only view purchase orders for your current branch.',
            );
        }

        $purchaseOrder->load(['supplier', 'branch', 'items.product']);

        return response()->json([
            'message' => 'Purchase order fetched successfully.',
            'data' => ['purchase_order' => (new PurchaseOrderResource($purchaseOrder))->resolve()],
        ]);
    }

    public function place(Request $request, PurchaseOrder $purchaseOrder): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        EnsuresPermission::one($user, 'inventory.manage');
        TenantScope::ensureSaloonAccess($user, (int) $purchaseOrder->saloon_id);

        $order = $this->orders->placeOrder($purchaseOrder);

        return response()->json([
            'message' => 'Purchase order placed successfully.',
            'data' => ['purchase_order' => (new PurchaseOrderResource($order))->resolve()],
        ]);
    }

    public function receive(Request $request, PurchaseOrder $purchaseOrder): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        EnsuresPermission::one($user, 'inventory.manage');
        TenantScope::ensureSaloonAccess($user, (int) $purchaseOrder->saloon_id);

        $order = $this->orders->receive($purchaseOrder, $user);

        return response()->json([
            'message' => 'Purchase order received successfully.',
            'data' => ['purchase_order' => (new PurchaseOrderResource($order))->resolve()],
        ]);
    }

    public function cancel(Request $request, PurchaseOrder $purchaseOrder): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        EnsuresPermission::one($user, 'inventory.manage');
        TenantScope::ensureSaloonAccess($user, (int) $purchaseOrder->saloon_id);

        $order = $this->orders->cancel($purchaseOrder);

        return response()->json([
            'message' => 'Purchase order cancelled successfully.',
            'data' => ['purchase_order' => (new PurchaseOrderResource($order))->resolve()],
        ]);
    }
}
