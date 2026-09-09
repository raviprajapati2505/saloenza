<?php

namespace App\Http\Middleware;

use App\Support\Subscription\SubscriptionEntitlements;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureSubscriptionAccess
{
    public function __construct(
        private readonly SubscriptionEntitlements $entitlements,
    ) {
    }

    /**
     * Enforce subscription access mode: locked salons are blocked except billing renewal;
     * read-only salons may view data but cannot mutate.
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
            return $next($request);
        }

        $user->loadMissing('saloon');
        $saloon = $user->saloon;

        if ($saloon === null) {
            return $next($request);
        }

        $accessMode = $this->entitlements->accessMode($saloon);

        if ($accessMode === SubscriptionEntitlements::ACCESS_FULL) {
            return $next($request);
        }

        if ($this->isRenewalExempt($request)) {
            return $next($request);
        }

        if ($this->isSalonConfigurationExempt($request)) {
            return $next($request);
        }

        if ($accessMode === SubscriptionEntitlements::ACCESS_LOCKED) {
            return response()->json([
                'message' => 'Your salon account is locked. Upgrade to a paid plan to continue using the portal.',
                'subscription_access_mode' => SubscriptionEntitlements::ACCESS_LOCKED,
            ], 403);
        }

        if ($accessMode === SubscriptionEntitlements::ACCESS_READ_ONLY && ! $request->isMethodSafe()) {
            return response()->json([
                'message' => 'Your subscription has expired. Renew to make changes — you can still view your existing data.',
                'subscription_access_mode' => SubscriptionEntitlements::ACCESS_READ_ONLY,
            ], 403);
        }

        return $next($request);
    }

    private function isRenewalExempt(Request $request): bool
    {
        if ($request->isMethodSafe() && $request->is('api/v1/me')) {
            return true;
        }

        $path = $request->path();

        return str_contains($path, 'subscription-plans')
            || str_contains($path, 'subscription/checkout')
            || str_contains($path, 'subscription/upgrade')
            || str_contains($path, 'billing/config')
            || $request->is('api/v1/subscription');
    }

    /** Salon portal configuration stays editable during read-only subscription mode. */
    private function isSalonConfigurationExempt(Request $request): bool
    {
        if ($request->isMethodSafe()) {
            return false;
        }

        $path = $request->path();

        return str_contains($path, 'salon/settings')
            || str_contains($path, 'salon/business-profile');
    }
}
