<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Actions\Auth\VerifyPasswordResetOtpAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Auth\VerifyPasswordResetOtpRequest;
use App\Http\Resources\Api\V1\Auth\PasswordResetOtpVerifiedResource;
use Illuminate\Http\JsonResponse;

class VerifyPasswordResetOtpController extends Controller
{
    public function __construct(
        private readonly VerifyPasswordResetOtpAction $verifyPasswordResetOtpAction,
    ) {}

    public function __invoke(VerifyPasswordResetOtpRequest $request): JsonResponse
    {
        $result = $this->verifyPasswordResetOtpAction->execute($request->validated());

        return (new PasswordResetOtpVerifiedResource($result))->response();
    }
}
