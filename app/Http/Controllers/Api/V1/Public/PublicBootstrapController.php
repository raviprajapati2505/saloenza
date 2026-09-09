<?php

namespace App\Http\Controllers\Api\V1\Public;

use App\Http\Controllers\Controller;
use App\Services\Platform\PlatformSettingsService;
use App\Support\Subscription\SubscriptionModules;
use Illuminate\Http\JsonResponse;

class PublicBootstrapController extends Controller
{
    public function __construct(
        private readonly PlatformSettingsService $platformSettingsService,
    ) {
    }

    public function platformBranding(): JsonResponse
    {
        return response()->json([
            'message' => 'Platform branding fetched successfully.',
            'data' => $this->platformSettingsService->brandingPayload(),
        ]);
    }

    public function subscriptionModules(): JsonResponse
    {
        return response()->json([
            'message' => 'Subscription modules fetched successfully.',
            'data' => SubscriptionModules::catalogPayload(),
        ]);
    }
}
