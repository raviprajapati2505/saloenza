<?php

namespace App\Http\Controllers\Api\V1\Customer;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Customer\StoreCustomerTagRequest;
use App\Http\Requests\Api\V1\Customer\UpdateCustomerTagRequest;
use App\Http\Resources\Api\V1\Common\MessageResponseResource;
use App\Http\Resources\Api\V1\Customer\CustomerTagResource;
use App\Models\CustomerTag;
use App\Models\User;
use App\Support\Api\ListQuery;
use App\Support\EnsuresPermission;
use App\Support\Tenant\TenantScope;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CustomerTagController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        EnsuresPermission::one($user, 'customers.view');

        $saloonId = TenantScope::resolveSaloonFilter(
            $user,
            $request->filled('saloon_id') ? (int) $request->integer('saloon_id') : null,
        );

        $validated = ListQuery::validate($request, []);

        $query = CustomerTag::query()
            ->withCount('customers')
            ->orderBy('name');

        if ($saloonId !== null) {
            $query->forSaloon($saloonId);
        }

        ListQuery::applySearch($query, $validated['search'] ?? null, ['name']);

        $paginator = ListQuery::paginate($query, $validated);

        return response()->json(ListQuery::responsePayload(
            'Customer tags fetched successfully.',
            'tags',
            $paginator,
            CustomerTagResource::class,
        ));
    }

    public function store(StoreCustomerTagRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $saloonId = TenantScope::resolveSaloonId($user, $request->validated('saloon_id'));

        $tag = CustomerTag::query()->create([
            'saloon_id' => $saloonId,
            'name' => trim((string) $request->validated('name')),
            'color' => $request->validated('color') ?? 'slate',
        ]);

        $tag->loadCount('customers');

        return response()->json([
            'message' => 'Customer tag created successfully.',
            'data' => [
                'tag' => (new CustomerTagResource($tag))->resolve(),
            ],
        ], 201);
    }

    public function update(UpdateCustomerTagRequest $request, CustomerTag $tag): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $this->ensureTagAccess($user, $tag);

        $tag->update([
            'name' => trim((string) $request->validated('name')),
            'color' => $request->validated('color') ?? $tag->color,
        ]);

        $tag = $tag->fresh()->loadCount('customers');

        return response()->json([
            'message' => 'Customer tag updated successfully.',
            'data' => [
                'tag' => (new CustomerTagResource($tag))->resolve(),
            ],
        ]);
    }

    public function destroy(Request $request, CustomerTag $tag): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        EnsuresPermission::one($user, 'customers.manage');
        $this->ensureTagAccess($user, $tag);

        $tag->delete();

        return (new MessageResponseResource([
            'message' => 'Customer tag deleted successfully.',
        ]))->response();
    }

    private function ensureTagAccess(User $user, CustomerTag $tag): void
    {
        if ($user->is_system_admin) {
            return;
        }

        if ($user->saloon_id === null || (int) $tag->saloon_id !== (int) $user->saloon_id) {
            throw new AuthorizationException('You do not have access to this customer tag.');
        }
    }
}
