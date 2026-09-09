<?php

namespace App\Http\Controllers\Api\V1\Product;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Product\StoreProductRequest;
use App\Http\Requests\Api\V1\Product\UpdateProductRequest;
use App\Http\Resources\Api\V1\Common\MessageResponseResource;
use App\Http\Resources\Api\V1\Product\ProductResource;
use App\Models\Product;
use App\Models\User;
use App\Support\Api\ListQuery;
use App\Support\EnsuresPermission;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        EnsuresPermission::one($user, 'products.view');

        $validated = ListQuery::validate($request, [
            'is_active' => ['sometimes', 'boolean'],
            'category_id' => ['sometimes', 'integer', 'exists:categories,id'],
        ]);

        $query = Product::query()->with('category')->latest('id');

        if (array_key_exists('is_active', $validated)) {
            $query->where('is_active', (bool) $validated['is_active']);
        }

        if (array_key_exists('category_id', $validated)) {
            $query->where('category_id', (int) $validated['category_id']);
        }

        ListQuery::applySearch($query, $validated['search'] ?? null);

        $paginator = ListQuery::paginate($query, $validated);

        return response()->json(ListQuery::responsePayload(
            'Products fetched successfully.',
            'products',
            $paginator,
            ProductResource::class,
        ));
    }

    public function store(StoreProductRequest $request): JsonResponse
    {
        $product = Product::query()->create([
            'name' => trim((string) $request->validated('name')),
            'category_id' => (int) $request->validated('category_id'),
            'is_active' => (bool) $request->validated('is_active'),
        ]);

        $product->load('category');

        return response()->json([
            'message' => 'Product created successfully.',
            'data' => [
                'product' => (new ProductResource($product))->resolve(),
            ],
        ], 201);
    }

    public function show(Request $request, Product $product): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        EnsuresPermission::one($user, 'products.view');

        $product->loadMissing('category');

        return response()->json([
            'message' => 'Product fetched successfully.',
            'data' => [
                'product' => (new ProductResource($product))->resolve(),
            ],
        ]);
    }

    public function update(UpdateProductRequest $request, Product $product): JsonResponse
    {
        $product->update([
            'name' => trim((string) $request->validated('name')),
            'category_id' => (int) $request->validated('category_id'),
            'is_active' => (bool) $request->validated('is_active'),
        ]);

        $product->load('category');

        return response()->json([
            'message' => 'Product updated successfully.',
            'data' => [
                'product' => (new ProductResource($product))->resolve(),
            ],
        ]);
    }

    public function destroy(Request $request, Product $product): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        EnsuresPermission::one($user, 'products.delete');

        if (! $user->isPlatformActor()) {
            abort(403, 'Only platform administrators can delete master products.');
        }

        $product->delete();

        return (new MessageResponseResource([
            'message' => 'Product deleted successfully.',
        ]))->response();
    }
}
