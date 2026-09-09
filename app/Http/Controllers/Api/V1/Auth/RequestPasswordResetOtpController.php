<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Actions\Auth\RequestPasswordResetOtpAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Auth\RequestPasswordResetOtpRequest;
use App\Http\Resources\Api\V1\Auth\PasswordResetOtpRequestedResource;
use Illuminate\Http\JsonResponse;

class RequestPasswordResetOtpController extends Controller
{
    public function __construct(
        private readonly RequestPasswordResetOtpAction $requestPasswordResetOtpAction,
    ) {}

    public function __invoke(RequestPasswordResetOtpRequest $request): JsonResponse
    {
        $result = $this->requestPasswordResetOtpAction->execute($request->validated());

        return (new PasswordResetOtpRequestedResource($result))->response();
    }
}
