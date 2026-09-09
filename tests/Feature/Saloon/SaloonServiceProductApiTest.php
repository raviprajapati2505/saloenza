<?php

namespace Tests\Feature\Saloon;

use App\Models\Product;
use App\Models\SalonServiceProduct;
use App\Models\Saloon;
use App\Models\Service;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SaloonServiceProductApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_saloon_user_can_fetch_service_products(): void
    {
        $saloon = Saloon::query()->create([
            'name' => 'Glow Studio',
            'is_active' => true,
        ]);

        $service = Service::query()->create([
            'name' => 'Haircut',
            'default_price' => 500,
            'duration_minutes' => 45,
        ]);

        $product = Product::query()->create([
            'name' => 'Shampoo',
            'is_active' => true,
        ]);

        SalonServiceProduct::query()->create([
            'saloon_id' => $saloon->id,
            'service_id' => $service->id,
            'product_id' => $product->id,
            'price' => 550,
            'duration_minutes' => 45,
            'is_active' => true,
        ]);

        SalonServiceProduct::query()->create([
            'saloon_id' => $saloon->id,
            'service_id' => $service->id,
            'product_id' => null,
            'price' => 500,
            'duration_minutes' => 30,
            'is_active' => true,
        ]);

        $user = User::factory()->create([
            'phone' => '+917777777781',
            'saloon_id' => $saloon->id,
        ]);

        Sanctum::actingAs($user);

        $response = $this->getJson("/api/v1/saloons/{$saloon->id}/service-products");

        $response
            ->assertOk()
            ->assertJsonPath('message', 'Saloon service products fetched successfully.')
            ->assertJsonCount(2, 'data.service_products')
            ->assertJsonFragment(['name' => 'Haircut'])
            ->assertJsonFragment(['name' => 'Shampoo']);
    }

    public function test_saloon_user_can_sync_service_products(): void
    {
        $saloon = Saloon::query()->create([
            'name' => 'Glow Studio',
            'is_active' => true,
        ]);

        $serviceA = Service::query()->create([
            'name' => 'Haircut',
            'default_price' => 500,
            'duration_minutes' => 45,
        ]);

        $serviceB = Service::query()->create([
            'name' => 'Facial',
            'default_price' => 800,
            'duration_minutes' => 60,
        ]);

        $productA = Product::query()->create([
            'name' => 'Shampoo',
            'is_active' => true,
        ]);

        $productB = Product::query()->create([
            'name' => 'Conditioner',
            'is_active' => true,
        ]);

        SalonServiceProduct::query()->create([
            'saloon_id' => $saloon->id,
            'service_id' => $serviceA->id,
            'product_id' => $productA->id,
            'price' => 550,
            'duration_minutes' => 45,
            'is_active' => true,
        ]);

        $user = User::factory()->create([
            'phone' => '+917777777782',
            'saloon_id' => $saloon->id,
        ]);

        Sanctum::actingAs($user);

        $response = $this->putJson("/api/v1/saloons/{$saloon->id}/service-products", [
            'service_products' => [
                [
                    'service_id' => $serviceA->id,
                    'product_id' => $productA->id,
                    'price' => 600,
                    'duration_minutes' => 45,
                    'is_active' => true,
                ],
                [
                    'service_id' => $serviceA->id,
                    'product_id' => $productB->id,
                    'price' => 620,
                    'duration_minutes' => 50,
                    'is_active' => true,
                ],
                [
                    'service_id' => $serviceB->id,
                    'product_id' => null,
                    'price' => 850,
                    'duration_minutes' => 60,
                    'is_active' => true,
                ],
            ],
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('message', 'Saloon service products updated successfully.')
            ->assertJsonCount(3, 'data.service_products')
            ->assertJsonPath('data.service_products.0.price', 600);

        $this->assertSame(3, SalonServiceProduct::query()->where('saloon_id', $saloon->id)->count());
        $this->assertDatabaseHas('salon_service_products', [
            'saloon_id' => $saloon->id,
            'service_id' => $serviceB->id,
            'product_id' => null,
            'price' => '850.00',
        ]);
        $this->assertDatabaseMissing('salon_service_products', [
            'saloon_id' => $saloon->id,
            'service_id' => $serviceA->id,
            'product_id' => $productA->id,
            'price' => '550.00',
        ]);
    }

    public function test_sync_with_empty_array_removes_all_service_products(): void
    {
        $saloon = Saloon::query()->create([
            'name' => 'Glow Studio',
            'is_active' => true,
        ]);

        $service = Service::query()->create([
            'name' => 'Haircut',
            'default_price' => 500,
            'duration_minutes' => 45,
        ]);

        SalonServiceProduct::query()->create([
            'saloon_id' => $saloon->id,
            'service_id' => $service->id,
            'product_id' => null,
            'price' => 500,
            'duration_minutes' => 45,
            'is_active' => true,
        ]);

        $user = User::factory()->create([
            'phone' => '+917777777783',
            'saloon_id' => $saloon->id,
        ]);

        Sanctum::actingAs($user);

        $this->putJson("/api/v1/saloons/{$saloon->id}/service-products", [
            'service_products' => [],
        ])
            ->assertOk()
            ->assertJsonCount(0, 'data.service_products');

        $this->assertSame(0, SalonServiceProduct::query()->where('saloon_id', $saloon->id)->count());
    }

    public function test_user_cannot_manage_another_saloons_service_products(): void
    {
        $saloonA = Saloon::query()->create([
            'name' => 'Saloon A',
            'is_active' => true,
        ]);

        $saloonB = Saloon::query()->create([
            'name' => 'Saloon B',
            'is_active' => true,
        ]);

        $user = User::factory()->create([
            'phone' => '+917777777784',
            'saloon_id' => $saloonA->id,
        ]);

        Sanctum::actingAs($user);

        $this->getJson("/api/v1/saloons/{$saloonB->id}/service-products")
            ->assertForbidden();

        $this->putJson("/api/v1/saloons/{$saloonB->id}/service-products", [
            'service_products' => [],
        ])->assertForbidden();
    }

    public function test_super_admin_can_manage_any_saloon_service_products(): void
    {
        $saloon = Saloon::query()->create([
            'name' => 'Glow Studio',
            'is_active' => true,
        ]);

        $service = Service::query()->create([
            'name' => 'Haircut',
            'default_price' => 500,
            'duration_minutes' => 45,
        ]);

        $admin = User::factory()->create([
            'phone' => '+917777777785',
            'is_system_admin' => true,
            'saloon_id' => null,
        ]);

        Sanctum::actingAs($admin);

        $this->putJson("/api/v1/saloons/{$saloon->id}/service-products", [
            'service_products' => [
                [
                    'service_id' => $service->id,
                    'product_id' => null,
                    'price' => 500,
                    'duration_minutes' => 45,
                    'is_active' => true,
                ],
            ],
        ])->assertOk();

        $this->getJson("/api/v1/saloons/{$saloon->id}/service-products")
            ->assertOk()
            ->assertJsonCount(1, 'data.service_products');
    }

    public function test_branch_manager_only_sees_own_branch_service_products_and_cannot_sync(): void
    {
        $this->seed(\Database\Seeders\PermissionSeeder::class);
        $this->seed(\Database\Seeders\RoleSeeder::class);
        $this->seed(\Database\Seeders\SubscriptionPlanSeeder::class);

        $owner = $this->createOwnerUser([
            'email' => 'owner-ssp-scope@test.com',
            'phone' => '+917100000351',
        ]);
        $this->assignDefaultSubscription($owner->saloon, 'pro');

        $andheri = \App\Models\SaloonBranch::query()->create([
            'saloon_id' => $owner->saloon_id,
            'branch_name' => 'Andheri',
            'business_address_1' => 'Andheri Road',
            'city' => 'Mumbai',
            'state' => 'Maharashtra',
            'area_pincode' => '400053',
            'country' => 'India',
            'is_active' => true,
        ]);
        $bandra = \App\Models\SaloonBranch::query()->create([
            'saloon_id' => $owner->saloon_id,
            'branch_name' => 'Bandra',
            'business_address_1' => 'Bandra Road',
            'city' => 'Mumbai',
            'state' => 'Maharashtra',
            'area_pincode' => '400050',
            'country' => 'India',
            'is_active' => true,
        ]);

        $service = Service::query()->create([
            'name' => 'Haircut Scope',
            'default_price' => 500,
            'duration_minutes' => 30,
            'is_active' => true,
        ]);

        SalonServiceProduct::query()->create([
            'saloon_id' => $owner->saloon_id,
            'branch_id' => $andheri->id,
            'service_id' => $service->id,
            'product_id' => null,
            'price' => 500,
            'duration_minutes' => 30,
            'is_active' => true,
        ]);
        SalonServiceProduct::query()->create([
            'saloon_id' => $owner->saloon_id,
            'branch_id' => $bandra->id,
            'service_id' => $service->id,
            'product_id' => null,
            'price' => 700,
            'duration_minutes' => 45,
            'is_active' => true,
        ]);

        $managerRole = \App\Models\Role::findByCode(\App\Support\Role\RoleCodes::SALON_BRANCH_MANAGER);
        $manager = User::query()->create([
            'name' => 'SSP Manager',
            'firstname' => 'SSP',
            'lastname' => 'Manager',
            'email' => 'manager-ssp-scope@test.com',
            'phone' => '+917100000352',
            'password' => 'Pass@1234',
            'role_id' => $managerRole->id,
            'saloon_id' => $owner->saloon_id,
            'branch_id' => $andheri->id,
            'is_active' => true,
            'onboarding_completed_at' => now(),
        ]);

        Sanctum::actingAs($manager);

        $this->getJson("/api/v1/saloons/{$owner->saloon_id}/service-products")
            ->assertOk()
            ->assertJsonCount(1, 'data.service_products')
            ->assertJsonPath('data.service_products.0.branch_id', $andheri->id);

        $this->putJson("/api/v1/saloons/{$owner->saloon_id}/service-products", [
            'service_products' => [
                [
                    'branch_id' => $bandra->id,
                    'service_id' => $service->id,
                    'product_id' => null,
                    'price' => 999,
                    'duration_minutes' => 60,
                    'is_active' => true,
                ],
            ],
        ])
            ->assertForbidden()
            ->assertJsonPath('message', 'Branch managers must update offerings through the catalog API.');
    }
}
