<?php

namespace Tests\Feature\Auth;

use App\Models\Saloon;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LoginApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_login_with_email(): void
    {
        $saloon = Saloon::query()->create([
            'name' => 'Glow House',
        ]);

        $user = User::factory()->create([
            'email' => 'owner@example.com',
            'phone' => '+919898989898',
            'saloon_id' => $saloon->id,
            'password' => 'Pass@1234',
        ]);

        $this->postJson('/api/v1/public/login', [
            'login' => $user->email,
            'password' => 'Pass@1234',
            'device_name' => 'phpunit',
        ])->assertOk()
            ->assertJsonPath('message', 'Login successful.');
    }

    public function test_user_can_login_with_phone(): void
    {
        $saloon = Saloon::query()->create([
            'name' => 'Glow House',
        ]);

        $user = User::factory()->create([
            'email' => 'phone-login@example.com',
            'phone' => '+919797979797',
            'saloon_id' => $saloon->id,
            'password' => 'Pass@1234',
        ]);

        $this->postJson('/api/v1/public/login', [
            'login' => $user->phone,
            'password' => 'Pass@1234',
            'device_name' => 'phpunit',
        ])->assertOk()
            ->assertJsonPath('message', 'Login successful.');
    }

    public function test_inactive_user_cannot_login(): void
    {
        $saloon = Saloon::query()->create([
            'name' => 'Glow House',
        ]);

        $user = User::factory()->create([
            'email' => 'inactive@example.com',
            'phone' => '+919191919191',
            'saloon_id' => $saloon->id,
            'password' => 'Pass@1234',
            'is_active' => false,
        ]);

        $this->postJson('/api/v1/public/login', [
            'login' => $user->email,
            'password' => 'Pass@1234',
            'device_name' => 'phpunit',
        ])->assertUnprocessable()
            ->assertJsonPath('message', 'Your account has been deactivated.');
    }
}
