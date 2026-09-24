<?php

namespace App\Http\Controllers\Api\V1\Package;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\Package\CustomerPackageResource;
use App\Http\Resources\Api\V1\Package\PackageResource;
use App\Models\Package;
use App\Models\User;
use App\Services\Package\PackageService;
use App\Support\Api\ListQuery;
use App\Support\Appointment\AppointmentPayment;
use App\Support\EnsuresPermission;
use App\Support\Tenant\TenantScope;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PackageController extends Controller
{
    public function __construct(
        private readonly PackageService $packages,
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        EnsuresPermission::one($user, 'packages.view');
        $saloonId = TenantScope::resolveSaloonFilter($user, null);

        $validated = ListQuery::validate($request, [
            'is_active' => ['sometimes', 'boolean'],
            'branch_id' => ['sometimes', 'integer', 'exists:saloon_branches,id'],
        ]);

        $query = Package::query()
            ->with(['items.service', 'branch', 'creator'])
            ->withCount(['items', 'customerPackages'])
            ->when($saloonId !== null, fn ($q) => $q->where('saloon_id', $saloonId))
            ->latest('id');

        if (array_key_exists('is_active', $validated)) {
            $query->where('is_active', (bool) $validated['is_active']);
        }

        if (! empty($validated['branch_id'])) {
            $query->where(function ($inner) use ($validated): void {
                $inner->whereNull('branch_id')->orWhere('branch_id', (int) $validated['branch_id']);
            });
        }

        ListQuery::applySearch($query, $validated['search'] ?? null, ['name', 'description']);
        $paginator = ListQuery::paginate($query, $validated);

        return response()->json(ListQuery::responsePayload(
            'Packages fetched successfully.',
            'packages',
            $paginator,
            PackageResource::class,
        ));
    }

    public function store(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        EnsuresPermission::one($user, 'packages.manage');

        $validated = $request->validate($this->rules($user));
        $package = $this->packages->create($user, $validated);

        return response()->json([
            'message' => 'Package created successfully.',
            'data' => ['package' => (new PackageResource($package))->resolve()],
        ], 201);
    }

    public function show(Request $request, Package $package): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        EnsuresPermission::one($user, 'packages.view');
        TenantScope::ensureSaloonAccess($user, (int) $package->saloon_id);

        $package->load(['items.service', 'branch', 'creator'])->loadCount(['items', 'customerPackages']);

        return response()->json([
            'message' => 'Package fetched successfully.',
            'data' => ['package' => (new PackageResource($package))->resolve()],
        ]);
    }

    public function update(Request $request, Package $package): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        EnsuresPermission::one($user, 'packages.manage');
        TenantScope::ensureSaloonAccess($user, (int) $package->saloon_id);

        $validated = $request->validate($this->rules($user, partial: true));
        $package = $this->packages->update($package, $validated);

        return response()->json([
            'message' => 'Package updated successfully.',
            'data' => ['package' => (new PackageResource($package))->resolve()],
        ]);
    }

    public function destroy(Request $request, Package $package): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        EnsuresPermission::one($user, 'packages.manage');
        TenantScope::ensureSaloonAccess($user, (int) $package->saloon_id);

        $package = $this->packages->deactivate($package);

        return response()->json([
            'message' => 'Package deactivated successfully.',
            'data' => ['package' => (new PackageResource($package))->resolve()],
        ]);
    }

    public function sell(Request $request, Package $package): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        EnsuresPermission::one($user, 'packages.sell');
        TenantScope::ensureSaloonAccess($user, (int) $package->saloon_id);

        $validated = $request->validate([
            'customer_id' => ['required', 'integer', 'exists:customers,id'],
            'branch_id' => [
                'nullable',
                'integer',
                Rule::exists('saloon_branches', 'id')->where(
                    fn ($q) => $q->where('saloon_id', (int) $user->saloon_id),
                ),
            ],
            'amount_paid' => ['nullable', 'numeric', 'min:0', 'max:99999999'],
            'payment_method' => ['nullable', 'string', Rule::in(AppointmentPayment::METHODS)],
            'payment_ref' => ['nullable', 'string', 'max:120'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $sold = $this->packages->sell($user, $package, $validated);

        return response()->json([
            'message' => 'Package sold successfully.',
            'data' => ['customer_package' => (new CustomerPackageResource($sold))->resolve()],
        ], 201);
    }

    /**
     * @return array<string, mixed>
     */
    private function rules(User $user, bool $partial = false): array
    {
        $required = $partial ? 'sometimes' : 'required';

        return [
            'name' => [$required, 'string', 'max:160'],
            'description' => ['nullable', 'string', 'max:5000'],
            'price' => [$required, 'numeric', 'min:0', 'max:99999999'],
            'valid_days' => ['nullable', 'integer', 'min:1', 'max:3650'],
            'is_active' => ['sometimes', 'boolean'],
            'branch_id' => [
                'nullable',
                'integer',
                Rule::exists('saloon_branches', 'id')->where(
                    fn ($q) => $q->where('saloon_id', (int) $user->saloon_id),
                ),
            ],
            'items' => [$partial ? 'sometimes' : 'required', 'array', 'min:1'],
            'items.*.service_id' => ['required_with:items', 'integer', 'exists:services,id'],
            'items.*.quantity_total' => ['required_with:items', 'integer', 'min:1', 'max:500'],
            'items.*.unit_value' => ['nullable', 'numeric', 'min:0', 'max:99999999'],
        ];
    }
}
