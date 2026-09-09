<?php

namespace App\Repositories\Contracts;

use App\Models\Saloon;

interface SaloonRepositoryInterface
{
    /**
     * @param array<string, mixed> $attributes
     */
    public function create(array $attributes): Saloon;

    /**
     * @param array<string, mixed> $attributes
     */
    public function update(Saloon $saloon, array $attributes): bool;
}
