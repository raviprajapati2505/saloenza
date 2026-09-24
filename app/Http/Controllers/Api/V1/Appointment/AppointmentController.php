<?php

namespace App\Http\Controllers\Api\V1\Appointment;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Appointment\StoreAppointmentRequest;
use App\Http\Requests\Api\V1\Appointment\UpdateAppointmentRequest;
use App\Http\Resources\Api\V1\Appointment\AppointmentResource;
use App\Http\Resources\Api\V1\Common\MessageResponseResource;
use App\Models\Appointment;
use App\Models\Customer;
use App\Support\Catalog\SalonOfferingResolver;
use App\Models\SaloonBranch;
use App\Models\User;
use App\Services\Appointment\AppointmentBookingService;
use App\Services\Appointment\AppointmentNotificationService;
use App\Services\Waitlist\WaitlistService;
use App\Support\Api\ListQuery;
use App\Support\Appointment\AppointmentPayment;
use App\Support\Appointment\AppointmentStatus;
use App\Support\Branch\BranchScope;
use App\Support\Customer\CustomerContactAccess;
use App\Support\Customer\CustomerContactPayload;
use App\Support\EnsuresPermission;
use App\Support\Role\RoleCodes;
use App\Support\Tenant\TenantScope;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AppointmentController extends Controller
{
    public function __construct(
        private readonly AppointmentBookingService $booking,
        private readonly AppointmentNotificationService $appointmentNotifications,
        private readonly WaitlistService $waitlist,
    ) {}

    public function index(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        EnsuresPermission::one($user, 'appointments.view');

        $saloonId = TenantScope::resolveSaloonFilter(
            $user,
            $request->filled('saloon_id') ? (int) $request->integer('saloon_id') : null,
        );

        $validated = ListQuery::validate($request, [
            'status' => ['sometimes', 'string', 'max:30'],
            // Comma separated so the POS can pull walk-ins and counter sales together.
            'type' => ['sometimes', 'string', 'regex:/^(appointment|walk_in|product_sale)(,(appointment|walk_in|product_sale))*$/'],
            'staff_id' => ['sometimes', 'integer', 'exists:users,id'],
            'branch_id' => ['sometimes', 'integer', 'exists:saloon_branches,id'],
            'customer_id' => ['sometimes', 'integer', 'exists:customers,id'],
            'from' => ['sometimes', 'date'],
            'payment_status' => ['sometimes', 'string', Rule::in(AppointmentPayment::STATUSES)],
            'to' => ['sometimes', 'date'],
        ]);

        $query = Appointment::query()
            ->with([...$this->booking->relations(), 'saloon'])
            ->orderBy('starts_at');

        if ($saloonId !== null) {
            $query->where('saloon_id', $saloonId);
        }

        // Branch-scoped actors are always locked to their assigned branch.
        if ($user->isBranchScopedActor()) {
            BranchScope::ensureAssigned($user);
            $query->where('branch_id', $user->branch_id);
        } elseif (array_key_exists('branch_id', $validated)) {
            $query->where('branch_id', $validated['branch_id']);
        }

        if (array_key_exists('status', $validated)) {
            $query->where('status', $validated['status']);
        }

        if (array_key_exists('type', $validated)) {
            $query->whereIn('type', explode(',', (string) $validated['type']));
        }

        if (array_key_exists('staff_id', $validated)) {
            $staffId = $validated['staff_id'];
            $query->where(function ($inner) use ($staffId): void {
                $inner->where('staff_id', $staffId)
                    ->orWhereHas('services', fn ($serviceQuery) => $serviceQuery->where('staff_id', $staffId));
            });
        }

        if (array_key_exists('customer_id', $validated)) {
            $query->where('customer_id', $validated['customer_id']);
        }

        if (array_key_exists('payment_status', $validated)) {
            $query->where('payment_status', $validated['payment_status']);
        }

        if (array_key_exists('from', $validated)) {
            $query->whereDate('starts_at', '>=', $validated['from']);
        }

        if (array_key_exists('to', $validated)) {
            $query->whereDate('starts_at', '<=', $validated['to']);
        }

        if (! empty($validated['search'])) {
            $search = $validated['search'];
            $query->whereHas('customer', function ($customerQuery) use ($user, $search): void {
                $customerQuery->where('name', 'like', "%{$search}%");

                if (CustomerContactAccess::canSearchByContact($user)) {
                    $customerQuery->orWhere('phone', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%");

                    return;
                }

                $customerQuery->orWhere(function ($owned) use ($user, $search): void {
                    $owned->where('created_by', $user->id)
                        ->where(function ($contact) use ($search): void {
                            $contact->where('phone', 'like', "%{$search}%")
                                ->orWhere('email', 'like', "%{$search}%");
                        });
                });
            });
        }

        $paginator = ListQuery::paginate($query, $validated);

        return response()->json(ListQuery::responsePayload(
            'Appointments fetched successfully.',
            'appointments',
            $paginator,
            AppointmentResource::class,
        ));
    }

    public function store(StoreAppointmentRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $this->booking->assertCanMutate($user);

        $payload = $this->booking->applyActorDefaults($user, $request->validated());
        $saloonId = TenantScope::resolveSaloonId($user, $payload['saloon_id'] ?? null);
        $payload['saloon_id'] = $saloonId;
        $payload['customer_id'] = $this->booking->resolveCustomerId($payload, $saloonId, null, $user);

        $appointment = $this->booking->create($user, $payload);
        $this->appointmentNotifications->appointmentCreated($appointment);

        return response()->json([
            'message' => 'Appointment created successfully.',
            'data' => [
                'appointment' => (new AppointmentResource($appointment))->resolve(),
            ],
        ], 201);
    }

    public function show(Request $request, Appointment $appointment): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        EnsuresPermission::one($user, 'appointments.view');
        TenantScope::ensureSaloonAccess($user, (int) $appointment->saloon_id);
        BranchScope::ensureSameBranch(
            $user,
            $appointment->branch_id !== null ? (int) $appointment->branch_id : null,
            'You can only view appointments for your current branch.',
        );

        $appointment->load([...$this->booking->relations(), 'saloon']);

        return response()->json([
            'message' => 'Appointment fetched successfully.',
            'data' => [
                'appointment' => (new AppointmentResource($appointment))->resolve(),
            ],
        ]);
    }

    public function update(UpdateAppointmentRequest $request, Appointment $appointment): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $this->booking->assertCanMutate($user);
        TenantScope::ensureSaloonAccess($user, (int) $appointment->saloon_id);
        BranchScope::ensureSameBranch(
            $user,
            $appointment->branch_id !== null ? (int) $appointment->branch_id : null,
            'You can only update appointments for your current branch.',
        );

        $payload = $this->booking->applyActorDefaults($user, $request->validated());
        $payload['customer_id'] = $this->booking->resolveCustomerId(
            $payload,
            (int) $appointment->saloon_id,
            $appointment->customer_id,
            $user,
        );

        $previousStatus = $appointment->status;
        $appointment = $this->booking->update($appointment, $payload, $user);
        $this->appointmentNotifications->appointmentUpdated($appointment, $previousStatus);

        if (
            $previousStatus !== AppointmentStatus::CANCELLED
            && $appointment->status === AppointmentStatus::CANCELLED
        ) {
            try {
                $this->waitlist->onSlotReleased($appointment);
            } catch (\Throwable) {
                // Waitlist matching must never block appointment cancellation.
            }
        }

        return response()->json([
            'message' => 'Appointment updated successfully.',
            'data' => [
                'appointment' => (new AppointmentResource($appointment))->resolve(),
            ],
        ]);
    }

    public function destroy(Request $request, Appointment $appointment): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        EnsuresPermission::one($user, 'appointments.delete');
        $this->booking->assertCanMutate($user);
        TenantScope::ensureSaloonAccess($user, (int) $appointment->saloon_id);
        BranchScope::ensureSameBranch(
            $user,
            $appointment->branch_id !== null ? (int) $appointment->branch_id : null,
            'You can only delete appointments for your current branch.',
        );

        $appointment->delete();

        return (new MessageResponseResource([
            'message' => 'Appointment deleted successfully.',
        ]))->response();
    }

    /**
     * Bootstrap data for the booking form: branches, staff and the salon's bookable services.
     */
    public function bookingOptions(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        EnsuresPermission::one($user, 'appointments.view');

        $saloonId = TenantScope::resolveSaloonFilter(
            $user,
            $request->filled('saloon_id') ? (int) $request->integer('saloon_id') : null,
        );

        $branchId = $user->isBranchScopedActor() && $user->branch_id
            ? (int) $user->branch_id
            : ($request->filled('branch_id') ? (int) $request->integer('branch_id') : null);

        $branches = SaloonBranch::query()
            ->when($saloonId !== null, fn ($query) => $query->where('saloon_id', $saloonId))
            ->when($branchId !== null, fn ($query) => $query->where('id', $branchId))
            ->where('is_active', true)
            ->orderBy('branch_name')
            ->get(['id', 'branch_name'])
            ->map(fn (SaloonBranch $branch): array => [
                'id' => $branch->id,
                'name' => $branch->branch_name,
            ]);

        $staffQuery = User::query()
            ->staff()
            ->when($saloonId !== null, fn ($query) => $query->where('saloon_id', $saloonId))
            ->when($branchId !== null, fn ($query) => $query->where('branch_id', $branchId))
            ->where('is_active', true)
            ->orderBy('name');

        if ($user->hasRoleCode(RoleCodes::SALON_STAFF)) {
            $staffQuery->where('id', $user->id);
        }

        $staff = $staffQuery
            ->get(['id', 'name', 'branch_id'])
            ->map(fn (User $member): array => [
                'id' => $member->id,
                'name' => $member->name,
                'branch_id' => $member->branch_id,
            ]);

        $services = $this->bookableServices($saloonId, $branchId);

        return response()->json([
            'message' => 'Booking options fetched successfully.',
            'data' => [
                'branches' => $branches,
                'staff' => $staff,
                'services' => $services,
                'defaults' => [
                    'branch_id' => $user->branch_id,
                    'staff_id' => $user->hasRoleCode(RoleCodes::SALON_STAFF) ? $user->id : null,
                    'lock_branch' => $user->isBranchScopedActor() && (bool) $user->branch_id,
                    'lock_staff' => $user->hasRoleCode(RoleCodes::SALON_STAFF),
                    'can_mutate' => ! $user->grantsAllPermissions(),
                ],
            ],
        ]);
    }

    /**
     * Typeahead customer lookup for the booking form (scoped to the appointment permission).
     */
    public function customerSearch(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        EnsuresPermission::one($user, 'appointments.view');

        $saloonId = TenantScope::resolveSaloonFilter(
            $user,
            $request->filled('saloon_id') ? (int) $request->integer('saloon_id') : null,
        );

        $search = trim((string) $request->query('search', ''));

        $customers = Customer::query()
            ->when($saloonId !== null, fn ($query) => $query->forSaloon($saloonId))
            ->when($search !== '', function ($query) use ($user, $search): void {
                $query->where(function ($inner) use ($user, $search): void {
                    $inner->where('name', 'like', "%{$search}%");

                    if (CustomerContactAccess::canSearchByContact($user)) {
                        $inner->orWhere('phone', 'like', "%{$search}%")
                            ->orWhere('email', 'like', "%{$search}%");

                        return;
                    }

                    $inner->orWhere(function ($owned) use ($user, $search): void {
                        $owned->where('created_by', $user->id)
                            ->where(function ($contact) use ($search): void {
                                $contact->where('phone', 'like', "%{$search}%")
                                    ->orWhere('email', 'like', "%{$search}%");
                            });
                    });
                });
            })
            ->orderBy('name')
            ->limit(10)
            ->get(['id', 'name', 'phone', 'email', 'created_by'])
            ->map(fn (Customer $customer): array => CustomerContactPayload::identity($customer, $user));

        return response()->json([
            'message' => 'Customers fetched successfully.',
            'data' => [
                'customers' => $customers,
            ],
        ]);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function bookableServices(?int $saloonId, ?int $branchId = null): array
    {
        if ($saloonId === null) {
            return [];
        }

        return SalonOfferingResolver::bookableServices($saloonId, $branchId);
    }
}
