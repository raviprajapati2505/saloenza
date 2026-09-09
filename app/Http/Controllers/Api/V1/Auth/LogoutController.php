<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\Common\MessageResponseResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LogoutController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $token = $request->user()?->currentAccessToken();

        if ($token !== null) {
            $token->delete();
        }

        return (new MessageResponseResource([
            'message' => 'Logged out successfully.',
        ]))->response();
    }
}
