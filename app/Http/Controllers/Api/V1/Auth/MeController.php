<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\Auth\CurrentUserResponseResource;
use App\Models\User;
use App\Repositories\Contracts\UserRepositoryInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MeController extends Controller
{
    public function __construct(
        private readonly UserRepositoryInterface $userRepository,
    ) {
    }

    public function __invoke(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $user = $this->userRepository->fresh($user, ['saloon', 'role.permissions', 'affiliatePartner']);

        return (new CurrentUserResponseResource($user))->response();
    }
}
