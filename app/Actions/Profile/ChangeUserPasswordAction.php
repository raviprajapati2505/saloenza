<?php

namespace App\Actions\Profile;

use App\Models\User;
use App\Repositories\Contracts\UserRepositoryInterface;
use Illuminate\Support\Facades\Hash;

class ChangeUserPasswordAction
{
    public function __construct(
        private readonly UserRepositoryInterface $userRepository,
    ) {
    }

    /**
     * @param array<string, mixed> $payload
     */
    public function execute(User $user, array $payload): void
    {
        $this->userRepository->update($user, [
            'password' => Hash::make((string) $payload['new_password']),
        ]);
    }
}
