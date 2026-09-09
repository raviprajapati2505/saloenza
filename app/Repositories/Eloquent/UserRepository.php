<?php

namespace App\Repositories\Eloquent;

use App\Models\User;
use App\Repositories\Contracts\UserRepositoryInterface;

class UserRepository implements UserRepositoryInterface
{
    public function create(array $attributes): User
    {
        return User::query()->create($attributes);
    }

    public function findByEmail(string $email, array $relations = []): ?User
    {
        return User::query()
            ->with($relations)
            ->where('email', strtolower(trim($email)))
            ->first();
    }

    public function findByEmailOrFail(string $email, array $relations = []): User
    {
        return User::query()
            ->with($relations)
            ->where('email', strtolower(trim($email)))
            ->firstOrFail();
    }

    public function update(User $user, array $attributes): bool
    {
        return $user->update($attributes);
    }

    public function fresh(User $user, array $relations = []): User
    {
        $user->refresh();

        if ($relations !== []) {
            $user->load($relations);
        }

        return $user;
    }

    public function createToken(User $user, string $deviceName): string
    {
        return $user->createToken($deviceName)->plainTextToken;
    }
}
