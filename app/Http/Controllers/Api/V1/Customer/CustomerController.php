<?php

namespace App\Http\Controllers\Api\V1\Customer;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Customer\StoreCustomerRequest;
use App\Http\Requests\Api\V1\Customer\UpdateCustomerRequest;
use App\Http\Resources\Api\V1\Common\MessageResponseResource;
use App\Http\Resources\Api\V1\Customer\CustomerResource;
use App\Models\Customer;
use App\Models\CustomerTag;
use App\Models\User;
use App\Support\Api\ListQuery;
use App\Support\Customer\CustomerContactAccess;
use App\Support\EnsuresPermission;
use App\Support\Tenant\TenantScope;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class CustomerController extends Controller
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

        $validated = ListQuery::validate($request, [
            'is_active' => ['sometimes', 'boolean'],
            'tag_id' => ['sometimes', 'integer', 'exists:customer_tags,id'],
            'segment' => ['sometimes', 'string', 'in:new,returning,lapsed'],
        ]);

        $query = Customer::query()
            ->with(['saloons:id,name', 'tags:id,saloon_id,name,color'])
            ->withCount('appointments')
            ->latest('id');

        if ($saloonId !== null) {
            $query->forSaloon($saloonId);
        }

        if (array_key_exists('is_active', $validated)) {
            $query->where('is_active', (bool) $validated['is_active']);
        }

        if (! empty($validated['tag_id'])) {
            $query->whereHas('tags', fn ($inner) => $inner->where('customer_tags.id', (int) $validated['tag_id']));
        }

        if (! empty($validated['segment'])) {
            $this->applySegmentFilter($query, (string) $validated['segment']);
        }

        if (! empty($validated['search'])) {
            $search = (string) $validated['search'];
            $query->where(function ($inner) use ($user, $search): void {
                $inner->where('name', 'like', "%{$search}%");

                if (CustomerContactAccess::canSearchByContact($user)) {
                    $inner->orWhere('email', 'like', "%{$search}%")
                        ->orWhere('phone', 'like', "%{$search}%");

                    return;
                }

                $inner->orWhere(function ($owned) use ($user, $search): void {
                    $owned->where('created_by', $user->id)
                        ->where(function ($contact) use ($search): void {
                            $contact->where('email', 'like', "%{$search}%")
                                ->orWhere('phone', 'like', "%{$search}%");
                        });
                });
            });
        }

        $paginator = ListQuery::paginate($query, $validated);

        return response()->json(ListQuery::responsePayload(
            'Customers fetched successfully.',
            'customers',
            $paginator,
            CustomerResource::class,
        ));
    }

    public function store(StoreCustomerRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $saloonId = TenantScope::resolveSaloonId($user, $request->validated('saloon_id'));

        $customer = Customer::query()->create([
            'name' => trim((string) $request->validated('name')),
            'email' => $request->validated('email'),
            'phone' => $request->validated('phone'),
            'notes' => $request->validated('notes'),
            'birthday' => $request->validated('birthday'),
            'anniversary' => $request->validated('anniversary'),
            'is_active' => (bool) $request->validated('is_active'),
            'created_by' => (int) $user->id,
        ]);

        $customer->attachSaloon($saloonId);
        $this->syncTags($customer, $request->validated('tag_ids') ?? [], $saloonId);
        $customer->load(['saloons:id,name', 'tags:id,saloon_id,name,color']);

        return response()->json([
            'message' => 'Customer created successfully.',
            'data' => [
                'customer' => (new CustomerResource($customer))->resolve(),
            ],
        ], 201);
    }

    public function show(Request $request, Customer $customer): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        EnsuresPermission::one($user, 'customers.view');
        TenantScope::ensureCustomerAccess($user, $customer);

        $customer->load([
            'saloons:id,name',
            'tags:id,saloon_id,name,color',
            'appointments' => fn ($query) => $query
                ->with(['service:id,name', 'services.service:id,name', 'branch:id,branch_name', 'staff:id,name'])
                ->latest('starts_at')
                ->limit(25),
        ]);

        $customer->loadCount('appointments');

        return response()->json([
            'message' => 'Customer fetched successfully.',
            'data' => [
                'customer' => (new CustomerResource($customer))->resolve(),
            ],
        ]);
    }

    public function update(UpdateCustomerRequest $request, Customer $customer): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        TenantScope::ensureCustomerAccess($user, $customer);

        $saloonId = $user->is_system_admin
            ? ($customer->saloons()->value('saloons.id') ?? TenantScope::resolveSaloonId($user, null))
            : (int) $user->saloon_id;

        $updates = [
            'name' => trim((string) $request->validated('name')),
            'notes' => $request->validated('notes'),
            'birthday' => $request->validated('birthday'),
            'anniversary' => $request->validated('anniversary'),
            'is_active' => (bool) $request->validated('is_active'),
        ];

        if (CustomerContactAccess::canView($user, $customer)) {
            $updates['email'] = $request->validated('email');
            $updates['phone'] = $request->validated('phone');
        }

        $customer->update($updates);

        if ($request->has('tag_ids')) {
            $customer->loadMissing('saloons:id');
            $saloonIds = $customer->saloons->pluck('id')->map(fn ($id) => (int) $id)->all();
            if ($saloonIds === []) {
                $saloonIds = [$saloonId];
            }
            $this->syncTags($customer, $request->validated('tag_ids') ?? [], $saloonIds);
        }

        $customer = $customer->fresh(['saloons:id,name', 'tags:id,saloon_id,name,color']);

        return response()->json([
            'message' => 'Customer updated successfully.',
            'data' => [
                'customer' => (new CustomerResource($customer))->resolve(),
            ],
        ]);
    }

    public function destroy(Request $request, Customer $customer): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        EnsuresPermission::one($user, 'customers.manage');
        TenantScope::ensureCustomerAccess($user, $customer);

        $customer->delete();

        return (new MessageResponseResource([
            'message' => 'Customer deleted successfully.',
        ]))->response();
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Builder<Customer>  $query
     */
    private function applySegmentFilter($query, string $segment): void
    {
        match ($segment) {
            'new' => $query->where('customers.created_at', '>=', now()->subDays(30)),
            'returning' => $query->has('appointments', '>=', 2),
            'lapsed' => $query
                ->whereHas('appointments')
                ->whereDoesntHave('appointments', fn ($inner) => $inner->where('starts_at', '>=', now()->subDays(90))),
            default => null,
        };
    }

    /**
     * @param  list<int>  $tagIds
     * @param  list<int>|int  $saloonIds
     */
    private function syncTags(Customer $customer, array $tagIds, array|int $saloonIds): void
    {
        $saloonIds = is_int($saloonIds) ? [$saloonIds] : array_values(array_unique(array_map('intval', $saloonIds)));
        $tagIds = array_values(array_unique(array_map('intval', $tagIds)));

        if ($tagIds === []) {
            $customer->tags()->sync([]);

            return;
        }

        $validTagIds = CustomerTag::query()
            ->whereIn('saloon_id', $saloonIds)
            ->whereIn('id', $tagIds)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();

        if (count($validTagIds) !== count($tagIds)) {
            throw ValidationException::withMessages([
                'tag_ids' => ['One or more tags are invalid for this salon.'],
            ]);
        }

        $customer->tags()->sync($validTagIds);
    }
}
