<?php

namespace App\Http\Controllers\Api\V1\Role;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Role\AssignRolePermissionsRequest;
use App\Http\Requests\Api\V1\Role\CloneRoleRequest;
use App\Http\Requests\Api\V1\Role\StoreRoleRequest;
use App\Http\Requests\Api\V1\Role\UpdateRoleRequest;
use App\Http\Resources\Api\V1\Common\MessageResponseResource;
use App\Http\Resources\Api\V1\Role\RoleResource;
use App\Models\Role;
use App\Models\User;
use App\Support\Api\ListQuery;
use App\Support\EnsuresPermission;
use App\Support\Role\RoleAccess;
use App\Support\Role\RoleCodeGenerator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RoleController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $validated = ListQuery::validate($request, [
            'saloon_id' => ['sometimes', 'nullable', 'integer', 'exists:saloons,id'],
            'is_active' => ['sometimes', 'boolean'],
            'scope' => ['sometimes', 'string', 'in:platform,affiliate,salon,branch'],
        ]);

        /** @var User $user */
        $user = $request->user();
        EnsuresPermission::one($user, 'roles.view');

        $query = Role::query()
            ->with('permissions')
            ->orderBy('hierarchy_level')
            ->orderBy('name');

        if ($user->is_system_admin) {
            if (array_key_exists('saloon_id', $validated) && $validated['saloon_id'] !== null) {
                $query->where('saloon_id', $validated['saloon_id']);
            }
        } else {
            RoleAccess::applyTenantScope($query, $user);
        }

        if (array_key_exists('is_active', $validated)) {
            $query->where('is_active', (bool) $validated['is_active']);
        }

        if (array_key_exists('scope', $validated)) {
            $query->where('scope', $validated['scope']);
        }

        ListQuery::applySearch($query, $validated['search'] ?? null, ['name', 'code']);

        $paginator = ListQuery::paginate($query, $validated);

        return response()->json(ListQuery::responsePayload(
            'Roles fetched successfully.',
            'roles',
            $paginator,
            RoleResource::class,
        ));
    }

    public function store(StoreRoleRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $saloonId = RoleAccess::resolveSaloonId($user, $request->validated('saloon_id'));
        $scope = RoleAccess::resolveScope($user, $request->validated('scope'));
        $branchId = RoleAccess::resolveBranchId(
            $user,
            $request->validated('branch_id') !== null ? (int) $request->validated('branch_id') : null,
        );
        $hierarchyLevel = RoleAccess::normalizeHierarchyLevel(
            $user,
            $request->validated('hierarchy_level') !== null ? (int) $request->validated('hierarchy_level') : null,
        );

        $code = $request->validated('code')
            ?? RoleCodeGenerator::forCustomRole((string) $request->validated('name'), $saloonId);

        $role = Role::query()->create([
            'name' => trim((string) $request->validated('name')),
            'code' => $code,
            'is_active' => (bool) $request->validated('is_active'),
            'is_system' => false,
            'scope' => $scope,
            'hierarchy_level' => $hierarchyLevel,
            'saloon_id' => $saloonId,
            'branch_id' => $branchId,
        ]);

        return response()->json([
            'message' => 'Role created successfully.',
            'data' => [
                'role' => (new RoleResource($role))->resolve(),
            ],
        ], 201);
    }

    public function show(Request $request, Role $role): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        EnsuresPermission::one($user, 'roles.view');
        RoleAccess::ensureAccessible($user, $role);
        $role->load('permissions');

        return response()->json([
            'message' => 'Role fetched successfully.',
            'data' => [
                'role' => (new RoleResource($role))->resolve(),
            ],
        ]);
    }

    public function update(UpdateRoleRequest $request, Role $role): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        RoleAccess::ensureMutable($user, $role);

        $role->update([
            'name' => trim((string) $request->validated('name')),
            'is_active' => (bool) $request->validated('is_active'),
            'saloon_id' => RoleAccess::resolveSaloonId(
                $user,
                $request->validated('saloon_id'),
            ),
            'branch_id' => RoleAccess::resolveBranchId(
                $user,
                $request->validated('branch_id') !== null ? (int) $request->validated('branch_id') : null,
            ),
        ]);

        return response()->json([
            'message' => 'Role updated successfully.',
            'data' => [
                'role' => (new RoleResource($role->fresh()))->resolve(),
            ],
        ]);
    }

    public function destroy(Request $request, Role $role): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        EnsuresPermission::one($user, 'roles.delete');
        RoleAccess::ensureDeletable($user, $role);
        $role->delete();

        return (new MessageResponseResource([
            'message' => 'Role deleted successfully.',
        ]))->response();
    }

    public function assignPermissions(AssignRolePermissionsRequest $request, Role $role): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        RoleAccess::ensurePermissionsAssignable($user, $role);

        $permissionIds = RoleAccess::filterAssignablePermissionIds(
            $user,
            $request->validated('permission_ids'),
        );

        $role->permissions()->sync($permissionIds);
        $role->load('permissions');

        return response()->json([
            'message' => 'Role permissions assigned successfully.',
            'data' => [
                'role' => (new RoleResource($role))->resolve(),
            ],
        ]);
    }

    public function clone(CloneRoleRequest $request, Role $role): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        RoleAccess::ensureAccessible($user, $role);

        $saloonId = RoleAccess::resolveSaloonId($user, $request->validated('saloon_id'));
        $name = trim((string) ($request->validated('name') ?? ($role->name.' Copy')));
        $scope = RoleAccess::resolveScope(
            $user,
            $role->scope === Role::SCOPE_PLATFORM ? Role::SCOPE_SALON : $role->scope,
        );
        $branchId = RoleAccess::resolveBranchId($user, $role->branch_id !== null ? (int) $role->branch_id : null);
        $hierarchyLevel = RoleAccess::normalizeHierarchyLevel(
            $user,
            min(99, (int) $role->hierarchy_level + 5),
        );

        $clone = Role::query()->create([
            'name' => $name,
            'code' => RoleCodeGenerator::forCustomRole($name, $saloonId),
            'is_active' => true,
            'is_system' => false,
            'scope' => $scope,
            'hierarchy_level' => $hierarchyLevel,
            'saloon_id' => $saloonId,
            'branch_id' => $branchId,
        ]);

        $clone->permissions()->sync(
            RoleAccess::filterAssignablePermissionIds(
                $user,
                $role->permissions()->pluck('permissions.id')->all(),
            ),
        );

        return response()->json([
            'message' => 'Role cloned successfully.',
            'data' => [
                'role' => (new RoleResource($clone->load('permissions')))->resolve(),
            ],
        ], 201);
    }
}
