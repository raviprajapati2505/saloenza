<?php

namespace App\Http\Middleware;

use App\Support\Subscription\SubscriptionEntitlements;
use App\Support\Subscription\SubscriptionModules;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureSubscriptionModule
{
    public function __construct(
        private readonly SubscriptionEntitlements $entitlements,
    ) {
    }

    /**
     * Require the salon's active subscription to include at least one module.
     * Pass multiple module keys separated by "|".
     *
     * @param Closure(Request): Response $next
     */
    public function handle(Request $request, Closure $next, string ...$moduleArguments): Response
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

        $modules = $this->expandModules($moduleArguments);

        if ($modules === []) {
            return $next($request);
        }

        $user->loadMissing('saloon');
        $saloon = $user->saloon;

        if ($saloon === null) {
            return response()->json([
                'message' => 'Forbidden. Tenant context required.',
            ], 403);
        }

        foreach ($modules as $module) {
            if ($this->entitlements->hasModule($saloon, $module)) {
                return $next($request);
            }
        }

        $planName = $this->entitlements->effectivePlan($saloon)?->name ?? 'your plan';
        $requiredLabel = implode(' or ', array_map(
            fn (string $module) => SubscriptionModules::labels()[$module] ?? $module,
            $modules,
        ));

        return response()->json([
            'message' => "The {$requiredLabel} module is not included in {$planName}. Please upgrade your subscription.",
            'required_modules' => $modules,
            'subscription_access_mode' => $this->entitlements->accessMode($saloon),
        ], 403);
    }

    /**
     * @param list<string> $moduleArguments
     * @return list<string>
     */
    private function expandModules(array $moduleArguments): array
    {
        $modules = [];

        foreach ($moduleArguments as $argument) {
            foreach (explode('|', $argument) as $module) {
                $module = trim($module);
                if ($module !== '' && in_array($module, SubscriptionModules::all(), true)) {
                    $modules[] = $module;
                }
            }
        }

        return array_values(array_unique($modules));
    }
}
