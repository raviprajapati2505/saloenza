<?php

namespace App\Http\Controllers\Api\V1\Referral;

use App\Http\Controllers\Controller;
use App\Services\Saloon\SalonReferralService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\HttpException;

class SalonReferralController extends Controller
{
    public function __construct(
        private readonly SalonReferralService $salonReferralService,
    ) {
    }

    public function dashboard(Request $request): JsonResponse
    {
        $saloon = $request->user()?->saloon;

        if ($saloon === null) {
            throw new HttpException(403, 'Salon context is required.');
        }

        return response()->json([
            'message' => 'Referral program fetched successfully.',
            'data' => $this->salonReferralService->buildDashboard($saloon),
        ]);
    }
}
