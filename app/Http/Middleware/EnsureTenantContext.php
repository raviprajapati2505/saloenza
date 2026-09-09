<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureTenantContext
{
    /**
     * Ensure the authenticated user belongs to a salon tenant (unless platform admin).
     *
     * @param Closure(Request): Response $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user === null) {
            return response()->json([
                'message' => 'Unauthenticated.',
            ], 401);
        }

        if ($user->grantsAllPermissions()) {
            return $next($request);
        }

        if ($user->saloon_id === null) {
            return response()->json([
                'message' => 'Forbidden. Tenant context required.',
            ], 403);
        }

        return $next($request);
    }
}
