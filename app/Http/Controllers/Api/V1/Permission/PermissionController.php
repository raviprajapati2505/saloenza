<?php

namespace App\Http\Controllers\Api\V1\Permission;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\Permission\PermissionResource;
use App\Models\Permission;
use App\Models\User;
use App\Support\EnsuresPermission;
use App\Support\UserPermissions;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PermissionController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        EnsuresPermission::any($user, ['assign_permissions.view', 'assign_permissions.update']);

        $permissions = Permission::query()
            ->when(
                ! $user->is_system_admin && ! $user->grantsAllPermissions(),
                function ($query) use ($user): void {
                    $codes = UserPermissions::codesFor($user)->all();
                    $query->where('scope', 'tenant')
                        ->whereIn('code', $codes !== [] ? $codes : ['__none__']);
                },
            )
            ->orderBy('module')
            ->orderBy('name')
            ->get();

        return response()->json([
            'message' => 'Permissions fetched successfully.',
            'data' => [
                'permissions' => PermissionResource::collection($permissions)->resolve(),
            ],
        ]);
    }
}
