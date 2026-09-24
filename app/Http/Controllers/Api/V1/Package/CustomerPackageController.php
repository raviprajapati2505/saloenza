<?php

namespace App\Http\Controllers\Api\V1\Package;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\Package\CustomerPackageResource;
use App\Models\Customer;
use App\Models\CustomerPackage;
use App\Models\User;
use App\Services\Package\PackageService;
use App\Support\Api\ListQuery;
use App\Support\EnsuresPermission;
use App\Support\Package\CustomerPackageStatus;
use App\Support\Tenant\TenantScope;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CustomerPackageController extends Controller
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
            'customer_id' => ['sometimes', 'integer', 'exists:customers,id'],
            'status' => ['sometimes', 'string', Rule::in(CustomerPackageStatus::ALL)],
            'package_id' => ['sometimes', 'integer', 'exists:packages,id'],
        ]);

        $query = CustomerPackage::query()
            ->with(['package', 'items.service', 'customer', 'seller', 'branch'])
            ->when($saloonId !== null, fn ($q) => $q->where('saloon_id', $saloonId))
            ->latest('purchased_at');

        if (! empty($validated['customer_id'])) {
            $query->where('customer_id', (int) $validated['customer_id']);
        }

        if (! empty($validated['status'])) {
            $query->where('status', $validated['status']);
        }

        if (! empty($validated['package_id'])) {
            $query->where('package_id', (int) $validated['package_id']);
        }

        $paginator = ListQuery::paginate($query, $validated);

        return response()->json(ListQuery::responsePayload(
            'Customer packages fetched successfully.',
            'customer_packages',
            $paginator,
            CustomerPackageResource::class,
        ));
    }

    public function forCustomer(Request $request, Customer $customer): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        EnsuresPermission::one($user, 'packages.view');

        $saloonId = (int) ($user->saloon_id ?? 0);
        if ($saloonId <= 0 || ! $customer->belongsToSaloon($saloonId)) {
            abort(404);
        }

        $activeOnly = $request->boolean('active_only', true);
        if ($activeOnly) {
            $rows = $this->packages->listActiveForCustomer($saloonId, (int) $customer->id);
        } else {
            $rows = CustomerPackage::query()
                ->with(['package', 'items.service', 'seller', 'branch'])
                ->where('saloon_id', $saloonId)
                ->where('customer_id', $customer->id)
                ->orderByDesc('purchased_at')
                ->get();
        }

        return response()->json([
            'message' => 'Customer packages fetched successfully.',
            'data' => [
                'customer_packages' => CustomerPackageResource::collection($rows)->resolve(),
            ],
        ]);
    }

    public function redeem(Request $request, CustomerPackage $customerPackage): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        EnsuresPermission::one($user, 'packages.redeem');
        TenantScope::ensureSaloonAccess($user, (int) $customerPackage->saloon_id);

        $validated = $request->validate([
            'service_id' => ['required', 'integer', 'exists:services,id'],
            'quantity' => ['sometimes', 'integer', 'min:1', 'max:50'],
            'appointment_id' => ['nullable', 'integer', 'exists:appointments,id'],
            'appointment_service_id' => ['nullable', 'integer'],
        ]);

        $updated = $this->packages->redeem($customerPackage, $validated);

        return response()->json([
            'message' => 'Package service redeemed successfully.',
            'data' => ['customer_package' => (new CustomerPackageResource($updated))->resolve()],
        ]);
    }
}
