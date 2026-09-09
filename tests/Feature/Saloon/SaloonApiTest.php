<?php

namespace Tests\Feature\Saloon;

use App\Models\Saloon;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SaloonApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_super_admin_can_search_and_paginate_saloons(): void
    {
        Saloon::query()->create([
            'name' => 'Downtown Studio',
            'referral_code' => 'REF001',
            'transaction_id' => 'TXN-100',
            'is_active' => true,
        ]);

        Saloon::query()->create([
            'name' => 'Uptown Salon',
            'referral_code' => 'REF002',
            'transaction_id' => 'TXN-200',
            'is_active' => false,
        ]);

        $admin = $this->createSystemAdmin([
            'phone' => '+917888888888',
        ]);

        Sanctum::actingAs($admin);

        $this->getJson('/api/v1/saloons?page=1&per_page=10&search=Downtown')
            ->assertOk()
            ->assertJsonCount(1, 'data.saloons')
            ->assertJsonPath('data.saloons.0.business_name', 'Downtown Studio')
            ->assertJsonPath('data.meta.total', 1);

        $this->getJson('/api/v1/saloons?page=1&per_page=10&search=TXN-200')
            ->assertOk()
            ->assertJsonCount(1, 'data.saloons')
            ->assertJsonPath('data.saloons.0.business_name', 'Uptown Salon');

        $activeCount = Saloon::where('is_active', true)->count();
        $this->getJson('/api/v1/saloons?page=1&per_page=10&is_active=1')
            ->assertOk()
            ->assertJsonCount(min(10, $activeCount), 'data.saloons')
            ->assertJsonPath('data.saloons.0.is_active', true);

        $totalSaloons = Saloon::count();
        $this->getJson('/api/v1/saloons?per_page=1&page=2')
            ->assertOk()
            ->assertJsonCount(1, 'data.saloons')
            ->assertJsonPath('data.meta.current_page', 2)
            ->assertJsonPath('data.meta.per_page', 1)
            ->assertJsonPath('data.meta.total', $totalSaloons)
            ->assertJsonPath('data.meta.last_page', (int) ceil($totalSaloons / 1));
    }

    public function test_non_system_admin_cannot_list_saloons(): void
    {
        $user = User::factory()->create([
            'phone' => '+917999999999',
            'is_system_admin' => false,
        ]);

        Sanctum::actingAs($user);

        $this->getJson('/api/v1/saloons?page=1&per_page=10')
            ->assertForbidden()
            ->assertJsonPath('message', 'Forbidden. Platform permission required.');
    }
}
