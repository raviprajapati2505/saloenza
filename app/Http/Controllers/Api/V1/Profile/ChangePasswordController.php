<?php

namespace App\Http\Controllers\Api\V1\Profile;

use App\Actions\Profile\ChangeUserPasswordAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Profile\ChangePasswordRequest;
use App\Http\Resources\Api\V1\Common\MessageResponseResource;
use Illuminate\Http\JsonResponse;

class ChangePasswordController extends Controller
{
    public function __construct(
        private readonly ChangeUserPasswordAction $changeUserPasswordAction,
    ) {
    }

    public function __invoke(ChangePasswordRequest $request): JsonResponse
    {
        $this->changeUserPasswordAction->execute(
            $request->user(),
            $request->validated(),
        );

        return (new MessageResponseResource([
            'message' => 'Password changed successfully.',
        ]))->response();
    }
}
