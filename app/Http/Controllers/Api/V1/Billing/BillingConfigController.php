<?php

namespace App\Http\Controllers\Api\V1\Billing;

use App\Http\Controllers\Controller;
use App\Support\Payment\PaymentConfig;
use Illuminate\Http\JsonResponse;

class BillingConfigController extends Controller
{
    public function show(): JsonResponse
    {
        return response()->json([
            'message' => 'Billing configuration fetched successfully.',
            'data' => [
                'billing' => PaymentConfig::publicConfig(),
            ],
        ]);
    }
}
