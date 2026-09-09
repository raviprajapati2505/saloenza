<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Actions\Auth\LoginUserAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Auth\LoginRequest;
use App\Http\Resources\Api\V1\Auth\LoginResponseResource;
use Illuminate\Http\JsonResponse;

class LoginController extends Controller
{
    public function __construct(
        private readonly LoginUserAction $loginUserAction,
    ) {
    }

    public function __invoke(LoginRequest $request): JsonResponse
    {
        $result = $this->loginUserAction->execute($request->validated());

        return (new LoginResponseResource($result))->response();
    }
}
