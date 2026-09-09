<?php

namespace App\Repositories\Eloquent;

use App\Models\Saloon;
use App\Repositories\Contracts\SaloonRepositoryInterface;

class SaloonRepository implements SaloonRepositoryInterface
{
    public function create(array $attributes): Saloon
    {
        return Saloon::query()->create($attributes);
    }

    public function update(Saloon $saloon, array $attributes): bool
    {
        return $saloon->update($attributes);
    }
}
