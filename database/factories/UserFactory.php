<?php

namespace Database\Factories;

use App\Models\Role;
use App\Models\Saloon;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    protected static ?string $password;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'firstname' => fake()->firstName(),
            'lastname' => fake()->lastName(),
            'email' => fake()->unique()->safeEmail(),
            'phone' => fake()->unique()->numerify('+919#########'),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
            'is_active' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }

    public function forSaloon(Saloon $saloon): static
    {
        return $this->state(fn (array $attributes) => [
            'saloon_id' => $saloon->id,
        ]);
    }

    public function withRole(Role $role): static
    {
        return $this->state(fn (array $attributes) => [
            'role_id' => $role->id,
        ]);
    }

    public function systemAdmin(): static
    {
        return $this->afterCreating(function (User $user): void {
            $user->forceFill(['is_system_admin' => true])->save();
        });
    }

    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }
}
