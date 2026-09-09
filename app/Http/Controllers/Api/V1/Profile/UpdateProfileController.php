<?php

namespace App\Http\Controllers\Api\V1\Profile;

use App\Actions\Profile\UpdateUserProfileAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Profile\UpdateProfileRequest;
use App\Http\Resources\Api\V1\Profile\ProfileResponseResource;
use Illuminate\Http\JsonResponse;

class UpdateProfileController extends Controller
{
    public function __construct(
        private readonly UpdateUserProfileAction $updateUserProfileAction,
    ) {
    }

    public function __invoke(UpdateProfileRequest $request): JsonResponse
    {
        $user = $this->updateUserProfileAction->execute(
            $request->user(),
            $request->validated(),
        );

        return (new ProfileResponseResource($user))->response();
    }
}
