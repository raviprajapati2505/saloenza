<?php

namespace App\Http\Controllers\Api\V1\Staff;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Staff\StoreStaffRequest;
use App\Http\Requests\Api\V1\Staff\UpdateStaffRequest;
use App\Http\Resources\Api\V1\Common\MessageResponseResource;
use App\Http\Resources\Api\V1\Staff\StaffResource;
use App\Models\Role;
use App\Models\Saloon;
use App\Models\User;
use App\Support\Api\ListQuery;
use App\Support\EnsuresPermission;
use App\Support\Role\RoleAccess;
use App\Support\Staff\StaffAccess;
use App\Support\Subscription\SubscriptionEntitlements;
use App\Support\Tenant\TenantScope;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StaffController extends Controller
{
    public function __construct(
        private readonly SubscriptionEntitlements $entitlements,
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        StaffAccess::ensureReadable($user);

        $validated = ListQuery::validate($request, [
            'saloon_id' => ['sometimes', 'nullable', 'integer', 'exists:saloons,id'],
            'branch_id' => ['sometimes', 'nullable', 'integer', 'exists:saloon_branches,id'],
            'role_id' => ['sometimes', 'nullable', 'integer', 'exists:roles,id'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $saloonId = TenantScope::resolveSaloonFilter(
            $user,
            array_key_exists('saloon_id', $validated) && $validated['saloon_id'] !== null
                ? (int) $validated['saloon_id']
                : null,
        );

        $query = User::query()
            ->staff()
            ->with(['role', 'saloon', 'branch'])
            ->latest('id');

        if ($saloonId !== null) {
            $query->where('saloon_id', $saloonId);
        }

        if (! $user->is_system_admin) {
            StaffAccess::applyBranchScope($query, $user);
        }

        if (array_key_exists('branch_id', $validated) && $validated['branch_id'] !== null) {
            $query->where('branch_id', $validated['branch_id']);
        }

        if (array_key_exists('role_id', $validated) && $validated['role_id'] !== null) {
            $query->where('role_id', $validated['role_id']);
        }

        if (array_key_exists('is_active', $validated)) {
            $query->where('is_active', (bool) $validated['is_active']);
        }

        ListQuery::applySearch(
            $query,
            $validated['search'] ?? null,
            ['name', 'firstname', 'lastname', 'email', 'phone'],
        );

        $paginator = ListQuery::paginate($query, $validated);

        return response()->json(ListQuery::responsePayload(
            'Staff fetched successfully.',
            'staff',
            $paginator,
            StaffResource::class,
        ));
    }

    public function assignableRoles(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        StaffAccess::ensureReadable($user);

        $validated = $request->validate([
            'saloon_id' => ['sometimes', 'nullable', 'integer', 'exists:saloons,id'],
        ]);

        $query = Role::query()
            ->where('is_active', true)
            ->orderBy('hierarchy_level')
            ->orderBy('name');

        if ($user->is_system_admin) {
            if (array_key_exists('saloon_id', $validated) && $validated['saloon_id'] !== null) {
                $query->where(function ($builder) use ($validated): void {
                    $builder->whereNull('saloon_id')
                        ->orWhere('saloon_id', $validated['saloon_id']);
                });
            }
        } else {
            RoleAccess::applyTenantScope($query, $user);

            if (! $user->grantsAllPermissions()) {
                $user->loadMissing('role');
                $actorLevel = (int) ($user->role?->hierarchy_level ?? 0);
                $query->where('hierarchy_level', '>=', $actorLevel);
            }
        }

        $roles = $query->get(['id', 'name', 'code', 'scope', 'is_active']);

        return response()->json([
            'message' => 'Assignable roles fetched successfully.',
            'data' => [
                'roles' => $roles->map(static fn (Role $role) => [
                    'id' => $role->id,
                    'name' => $role->name,
                    'code' => $role->code,
                    'scope' => $role->scope,
                    'is_active' => $role->is_active,
                ])->values()->all(),
            ],
        ]);
    }

    public function store(StoreStaffRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $saloonId = StaffAccess::resolveSaloonId(
            $user,
            $request->filled('saloon_id') ? (int) $request->validated('saloon_id') : null,
        );

        $saloon = Saloon::query()->findOrFail($saloonId);
        $this->entitlements->ensureCanAddStaff($saloon);

        $role = Role::query()->findOrFail((int) $request->validated('role_id'));
        RoleAccess::ensureAssignableToStaff($user, $role);

        $firstname = trim((string) $request->validated('firstname'));
        $lastname = trim((string) $request->validated('lastname'));

        $staff = User::query()->create([
            'name' => trim("{$firstname} {$lastname}"),
            'firstname' => $firstname,
            'lastname' => $lastname,
            'email' => $request->validated('email'),
            'phone' => $request->validated('phone'),
            'password' => $request->validated('password'),
            'is_active' => (bool) $request->validated('is_active'),
            'role_id' => (int) $request->validated('role_id'),
            'saloon_id' => $saloonId,
            'branch_id' => StaffAccess::resolveBranchId($user, $request->validated('branch_id')),
            'commission_rate' => $request->validated('commission_rate'),
            'per_month_salary' => $request->validated('per_month_salary'),
            'is_system_admin' => false,
            'notes' => $request->validated('notes'),
            'joined_at' => $request->validated('joined_at') ?? now()->toDateString(),
            'weekly_schedule' => $request->validated('weekly_schedule'),
        ]);

        $staff->forceFill(['is_system_admin' => false])->save();
        $staff->load(['role', 'saloon', 'branch']);

        return response()->json([
            'message' => 'Staff created successfully.',
            'data' => [
                'staff' => (new StaffResource($staff))->resolve(),
            ],
        ], 201);
    }

    public function show(Request $request, User $staff): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        StaffAccess::ensureReadable($user);
        StaffAccess::ensureWritable($user, $staff, 'staff.view');

        $staff->load(['role', 'saloon', 'branch']);

        return response()->json([
            'message' => 'Staff fetched successfully.',
            'data' => [
                'staff' => (new StaffResource($staff))->resolve(),
            ],
        ]);
    }

    public function update(UpdateStaffRequest $request, User $staff): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        StaffAccess::ensureWritable($user, $staff, 'staff.update');

        $role = Role::query()->findOrFail((int) $request->validated('role_id'));
        RoleAccess::ensureAssignableToStaff($user, $role);

        $firstname = trim((string) $request->validated('firstname'));
        $lastname = trim((string) $request->validated('lastname'));

        $attributes = [
            'name' => trim("{$firstname} {$lastname}"),
            'firstname' => $firstname,
            'lastname' => $lastname,
            'email' => $request->validated('email'),
            'phone' => $request->validated('phone'),
            'is_active' => (bool) $request->validated('is_active'),
            'role_id' => (int) $request->validated('role_id'),
            'branch_id' => StaffAccess::resolveBranchId(
                $user,
                $request->exists('branch_id') ? $request->validated('branch_id') : $staff->branch_id,
            ),
            'commission_rate' => $request->validated('commission_rate'),
            'per_month_salary' => $request->validated('per_month_salary'),
        ];

        foreach (['notes', 'joined_at', 'weekly_schedule'] as $optionalField) {
            if ($request->exists($optionalField)) {
                $attributes[$optionalField] = $request->validated($optionalField);
            }
        }

        if ($user->is_system_admin) {
            $attributes['saloon_id'] = (int) $request->validated('saloon_id');
        }

        if ($request->filled('password')) {
            $attributes['password'] = $request->validated('password');
        }

        $staff->update($attributes);
        $staff->load(['role', 'saloon', 'branch']);

        return response()->json([
            'message' => 'Staff updated successfully.',
            'data' => [
                'staff' => (new StaffResource($staff->fresh(['role', 'saloon', 'branch'])))->resolve(),
            ],
        ]);
    }

    public function destroy(Request $request, User $staff): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        EnsuresPermission::one($user, 'staff.delete');
        StaffAccess::ensureWritable($user, $staff, 'staff.delete');

        if ((int) $staff->id === (int) $user->id) {
            throw new AuthorizationException('You cannot delete your own account.');
        }

        $staff->delete();

        return (new MessageResponseResource([
            'message' => 'Staff deleted successfully.',
        ]))->response();
    }
}
