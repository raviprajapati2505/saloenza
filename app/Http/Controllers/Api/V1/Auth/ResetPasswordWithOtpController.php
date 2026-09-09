<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Actions\Auth\ResetPasswordWithOtpAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Auth\ResetPasswordWithOtpRequest;
use App\Http\Resources\Api\V1\Common\MessageResponseResource;
use Illuminate\Http\JsonResponse;

class ResetPasswordWithOtpController extends Controller
{
    public function __construct(
        private readonly ResetPasswordWithOtpAction $resetPasswordWithOtpAction,
    ) {}

    public function __invoke(ResetPasswordWithOtpRequest $request): JsonResponse
    {
        $this->resetPasswordWithOtpAction->execute($request->validated());

        return (new MessageResponseResource([
            'message' => 'Password reset successfully.',
        ]))->response();
    }
}
