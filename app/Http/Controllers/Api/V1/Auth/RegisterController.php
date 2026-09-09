<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Actions\Auth\RegisterSalonOwnerAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Auth\RegisterRequest;
use App\Http\Resources\Api\V1\Auth\RegisterResponseResource;
use Illuminate\Http\JsonResponse;

class RegisterController extends Controller
{
    public function __construct(
        private readonly RegisterSalonOwnerAction $registerSalonOwnerAction,
    ) {
    }

    public function __invoke(RegisterRequest $request): JsonResponse
    {
        $result = $this->registerSalonOwnerAction->execute($request->validated());

        return (new RegisterResponseResource($result))
            ->response()
            ->setStatusCode(201);
    }
}
