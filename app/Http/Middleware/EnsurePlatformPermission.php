<?php

namespace App\Http\Middleware;

use App\Support\UserPermissions;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsurePlatformPermission
{
    /**
     * @param Closure(Request): Response $next
     */
    public function handle(Request $request, Closure $next, string ...$permissionArguments): Response
    {
        $user = $request->user();

        if ($user === null) {
            return response()->json([
                'message' => 'Unauthenticated.',
            ], 401);
        }

        $permissions = $this->expandPermissions($permissionArguments);

        if ($user->grantsAllPermissions() || UserPermissions::allowsAny($user, $permissions)) {
            return $next($request);
        }

        return response()->json([
            'message' => 'Forbidden. Platform permission required.',
            'required_permissions' => $permissions,
        ], 403);
    }

    /**
     * @param  list<string>  $permissionArguments
     * @return list<string>
     */
    private function expandPermissions(array $permissionArguments): array
    {
        $permissions = [];

        foreach ($permissionArguments as $argument) {
            foreach (explode('|', $argument) as $code) {
                $code = trim($code);
                if ($code !== '') {
                    $permissions[] = $code;
                }
            }
        }

        return array_values(array_unique($permissions));
    }
}
