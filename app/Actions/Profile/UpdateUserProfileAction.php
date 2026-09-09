<?php

namespace App\Actions\Profile;

use App\Models\User;
use App\Repositories\Contracts\UserRepositoryInterface;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class UpdateUserProfileAction
{
    public function __construct(
        private readonly UserRepositoryInterface $userRepository,
    ) {
    }

    /**
     * @param array<string, mixed> $payload
     */
    public function execute(User $user, array $payload): User
    {
        $updates = [];

        if (array_key_exists('name', $payload)) {
            $updates['name'] = trim((string) $payload['name']);
        }

        if (array_key_exists('email', $payload)) {
            $updates['email'] = strtolower(trim((string) $payload['email']));
        }

        if (array_key_exists('phone', $payload)) {
            $updates['phone'] = $payload['phone'] !== null
                ? trim((string) $payload['phone'])
                : null;
        }

        if (isset($payload['photo']) && $payload['photo'] instanceof UploadedFile) {
            if ($user->photo) {
                Storage::disk('public')->delete($user->photo);
            }

            $updates['photo'] = $payload['photo']->store('profile-photos', 'public');
        }

        if ($updates !== []) {
            $this->userRepository->update($user, $updates);
        }

        return $this->userRepository->fresh($user);
    }
}
