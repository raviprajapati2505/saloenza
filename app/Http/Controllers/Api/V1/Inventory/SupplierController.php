<?php

namespace App\Http\Controllers\Api\V1\Inventory;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\Inventory\SupplierResource;
use App\Models\Supplier;
use App\Models\User;
use App\Support\Api\ListQuery;
use App\Support\EnsuresPermission;
use App\Support\Tenant\TenantScope;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SupplierController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        EnsuresPermission::one($user, 'inventory.view');
        $saloonId = TenantScope::resolveSaloonFilter($user, null);

        $validated = ListQuery::validate($request, [
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $query = Supplier::query()
            ->when($saloonId !== null, fn ($q) => $q->where('saloon_id', $saloonId))
            ->orderBy('name');

        if (array_key_exists('is_active', $validated)) {
            $query->where('is_active', (bool) $validated['is_active']);
        }

        ListQuery::applySearch($query, $validated['search'] ?? null);
        $paginator = ListQuery::paginate($query, $validated);

        return response()->json(ListQuery::responsePayload(
            'Suppliers fetched successfully.',
            'suppliers',
            $paginator,
            SupplierResource::class,
        ));
    }

    public function store(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        EnsuresPermission::one($user, 'inventory.manage');

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'contact_name' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:255'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $supplier = Supplier::query()->create([
            ...$validated,
            'saloon_id' => (int) $user->saloon_id,
            'is_active' => (bool) ($validated['is_active'] ?? true),
        ]);

        return response()->json([
            'message' => 'Supplier created successfully.',
            'data' => ['supplier' => (new SupplierResource($supplier))->resolve()],
        ], 201);
    }

    public function update(Request $request, Supplier $supplier): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        EnsuresPermission::one($user, 'inventory.manage');
        TenantScope::ensureSaloonAccess($user, (int) $supplier->saloon_id);

        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'contact_name' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:255'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $supplier->update($validated);

        return response()->json([
            'message' => 'Supplier updated successfully.',
            'data' => ['supplier' => (new SupplierResource($supplier->fresh()))->resolve()],
        ]);
    }

    public function destroy(Request $request, Supplier $supplier): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        EnsuresPermission::one($user, 'inventory.manage');
        TenantScope::ensureSaloonAccess($user, (int) $supplier->saloon_id);

        $supplier->delete();

        return response()->json(['message' => 'Supplier deleted successfully.']);
    }
}
