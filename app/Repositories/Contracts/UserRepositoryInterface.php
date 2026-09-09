<?php

namespace App\Repositories\Contracts;

use App\Models\User;

interface UserRepositoryInterface
{
    /**
     * @param array<string, mixed> $attributes
     */
    public function create(array $attributes): User;

    /**
     * @param array<int, string> $relations
     */
    public function findByEmail(string $email, array $relations = []): ?User;

    /**
     * @param array<int, string> $relations
     */
    public function findByEmailOrFail(string $email, array $relations = []): User;

    /**
     * @param array<string, mixed> $attributes
     */
    public function update(User $user, array $attributes): bool;

    /**
     * @param array<int, string> $relations
     */
    public function fresh(User $user, array $relations = []): User;

    public function createToken(User $user, string $deviceName): string;
}
