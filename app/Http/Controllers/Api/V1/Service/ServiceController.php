<?php

namespace App\Http\Controllers\Api\V1\Service;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Service\StoreServiceRequest;
use App\Http\Requests\Api\V1\Service\UpdateServiceRequest;
use App\Http\Resources\Api\V1\Common\MessageResponseResource;
use App\Http\Resources\Api\V1\Service\ServiceResource;
use App\Models\Service;
use App\Models\User;
use App\Support\Api\ListQuery;
use App\Support\EnsuresPermission;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ServiceController extends Controller
{
    public function index(Request $request): JsonResponse
    {

        /** @var User $user */
        $user = $request->user();
        EnsuresPermission::one($user, 'services.view');

        $validated = ListQuery::validate($request, [
            'is_active' => ['sometimes', 'boolean'],
            'category_id' => ['sometimes', 'nullable', 'integer', 'exists:categories,id'],
            'gender' => ['sometimes', 'nullable', 'string', 'in:Male,Female,Unisex'],
        ]);

        $query = Service::query()
            ->with(['serviceProducts.product', 'category'])
            ->latest('id');

        if (array_key_exists('is_active', $validated)) {
            $query->where('is_active', (bool) $validated['is_active']);
        }

        if (array_key_exists('category_id', $validated) && $validated['category_id'] !== null) {
            $query->where('category_id', $validated['category_id']);
        }

        if (array_key_exists('gender', $validated) && $validated['gender'] !== null) {
            $query->where('gender', $validated['gender']);
        }

        ListQuery::applySearch($query, $validated['search'] ?? null);

        $paginator = ListQuery::paginate($query, $validated);

        return response()->json(ListQuery::responsePayload(
            'Services fetched successfully.',
            'services',
            $paginator,
            ServiceResource::class,
        ));
    }

    public function store(StoreServiceRequest $request): JsonResponse
    {
        $service = DB::transaction(function () use ($request): Service {
            $service = Service::query()->create([
                'name' => trim((string) $request->validated('name')),
                'category_id' => (int) $request->validated('category_id'),
                'default_price' => $request->validated('default_price'),
                'duration_minutes' => (int) $request->validated('duration_minutes'),
                'gender' => $request->validated('gender'),
                'category_id' => $request->validated('category_id'),
                'is_active' => (bool) $request->validated('is_active'),
            ]);

            $this->syncProducts($service, $request->validated('products', []));

            return $service->load(['serviceProducts.product', 'category']);
        });

        return response()->json([
            'message' => 'Service created successfully.',
            'data' => [
                'service' => (new ServiceResource($service))->resolve(),
            ],
        ], 201);
    }

    public function show(Request $request, Service $service): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        EnsuresPermission::one($user, 'services.view');

        $service->load(['category', 'serviceProducts.product']);

        return response()->json([
            'message' => 'Service fetched successfully.',
            'data' => [
                'service' => (new ServiceResource($service))->resolve(),
            ],
        ]);
    }

    public function update(UpdateServiceRequest $request, Service $service): JsonResponse
    {
        $service = DB::transaction(function () use ($request, $service): Service {
            $service->update([
                'name' => trim((string) $request->validated('name')),
                'category_id' => (int) $request->validated('category_id'),
                'default_price' => $request->validated('default_price'),
                'duration_minutes' => (int) $request->validated('duration_minutes'),
                'gender' => $request->validated('gender'),
                'category_id' => $request->validated('category_id'),
                'is_active' => (bool) $request->validated('is_active'),
            ]);

            if ($request->has('products')) {
                $this->syncProducts($service, $request->validated('products', []));
            }

            return $service->fresh(['serviceProducts.product', 'category']);
        });

        return response()->json([
            'message' => 'Service updated successfully.',
            'data' => [
                'service' => (new ServiceResource($service))->resolve(),
            ],
        ]);
    }

    public function destroy(Request $request, Service $service): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        EnsuresPermission::one($user, 'services.delete');

        if (! $user->isPlatformActor()) {
            abort(403, 'Only platform administrators can delete master services.');
        }

        $service->delete();

        return (new MessageResponseResource([
            'message' => 'Service deleted successfully.',
        ]))->response();
    }

    /**
     * @param  array<int, array<string, mixed>>  $products
     */
    private function syncProducts(Service $service, array $products): void
    {
        $service->serviceProducts()->delete();

        foreach ($products as $product) {
            $service->serviceProducts()->create([
                'product_id' => (int) $product['product_id'],
                'default_price' => $product['default_price'],
            ]);
        }
    }
}
