<?php

namespace App\Http\Middleware;

use App\Support\Tenancy\SalonHostResolver;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureOnboardedSalonHost
{
    public function __construct(
        private readonly SalonHostResolver $salonHostResolver,
    ) {
    }

    /**
     * Block salon subdomains that are not onboarded. app.saloenza.com stays open.
     *
     * @param Closure(Request): Response $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->is('up')) {
            return $next($request);
        }

        $resolution = $this->salonHostResolver->resolve($request);

        if ($resolution->saloon !== null) {
            $request->attributes->set(SalonHostResolver::REQUEST_ATTRIBUTE, $resolution->saloon);
        }

        if (! $resolution->isMissing()) {
            return $next($request);
        }

        if ($request->is('api/*') || $request->expectsJson()) {
            return response()->json([
                'message' => 'This salon workspace was not found.',
            ], 404);
        }

        return response()->view('salon-not-found', [
            'host' => $resolution->host,
            'platformUrl' => $this->salonHostResolver->platformUrl(),
        ], 404);
    }
}
