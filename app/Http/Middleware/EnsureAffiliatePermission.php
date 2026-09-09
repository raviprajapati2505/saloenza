<?php

namespace App\Http\Middleware;

use App\Support\UserPermissions;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureAffiliatePermission
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

        $partner = $user->affiliatePartner;
        if ($partner !== null && ! $partner->isActive() && ! $user->grantsAllPermissions()) {
            return response()->json([
                'message' => $partner->isPending()
                    ? 'Your affiliate application is pending admin approval.'
                    : 'Your affiliate account is not active.',
                'affiliate_status' => $partner->status,
            ], 403);
        }

        $permissions = $this->expandPermissions($permissionArguments);

        if ($user->grantsAllPermissions() || UserPermissions::allowsAny($user, $permissions)) {
            return $next($request);
        }

        return response()->json([
            'message' => 'Forbidden. Affiliate permission required.',
            'required_permissions' => $permissions,
        ], 403);
    }

    /**
     * @param list<string> $permissionArguments
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
