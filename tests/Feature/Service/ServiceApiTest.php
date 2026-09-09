<?php

namespace Tests\Feature\Service;

use App\Models\Category;
use App\Models\Product;
use App\Models\Service;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ServiceApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_manage_services_with_products(): void
    {
        $category = Category::query()->create([
            'name' => 'Hair',
            'is_active' => true,
        ]);

        $productA = Product::query()->create([
            'name' => 'Shampoo',
            'category_id' => $category->id,
            'is_active' => true,
        ]);

        $productB = Product::query()->create([
            'name' => 'Conditioner',
            'category_id' => $category->id,
            'is_active' => true,
        ]);

        $category = Category::query()->create([
            'name' => 'Hair Care',
            'is_active' => true,
        ]);

        $this->actingAsSystemAdmin(['phone' => '+917777777780']);

        $createResponse = $this->postJson('/api/v1/services', [
            'name' => 'Hair Wash',
            'category_id' => $category->id,
            'default_price' => 299.50,
            'duration_minutes' => 30,
            'gender' => 'Unisex',
            'is_active' => true,
            'products' => [
                [
                    'product_id' => $productA->id,
                    'default_price' => 50,
                ],
                [
                    'product_id' => $productB->id,
                    'default_price' => 40,
                ],
            ],
        ]);

        $createResponse
            ->assertCreated()
            ->assertJsonPath('data.service.name', 'Hair Wash')
            ->assertJsonPath('data.service.category_id', $category->id)
            ->assertJsonPath('data.service.default_price', 299.5)
            ->assertJsonPath('data.service.duration_minutes', 30)
            ->assertJsonPath('data.service.gender', 'Unisex')
            ->assertJsonPath('data.service.is_active', true)
            ->assertJsonCount(2, 'data.service.products');

        $serviceId = (int) $createResponse->json('data.service.id');

        $this->getJson('/api/v1/services?page=1&per_page=10')
            ->assertOk()
            ->assertJsonCount(min(10, Service::count()), 'data.services');

        $this->getJson("/api/v1/services/{$serviceId}")
            ->assertOk()
            ->assertJsonPath('data.service.name', 'Hair Wash')
            ->assertJsonCount(2, 'data.service.products');

        $this->putJson("/api/v1/services/{$serviceId}", [
            'name' => 'Premium Hair Wash',
            'category_id' => $category->id,
            'default_price' => 499,
            'duration_minutes' => 45,
            'gender' => 'Female',
            'is_active' => false,
            'products' => [
                [
                    'product_id' => $productA->id,
                    'default_price' => 75,
                ],
            ],
        ])->assertOk()
            ->assertJsonPath('data.service.name', 'Premium Hair Wash')
            ->assertJsonPath('data.service.gender', 'Female')
            ->assertJsonPath('data.service.is_active', false)
            ->assertJsonCount(1, 'data.service.products');

        $this->assertDatabaseHas('services', [
            'id' => $serviceId,
            'is_active' => false,
        ]);

        $this->deleteJson("/api/v1/services/{$serviceId}")
            ->assertOk()
            ->assertJsonPath('message', 'Service deleted successfully.');

        $this->assertDatabaseMissing('services', [
            'id' => $serviceId,
        ]);

        $this->assertDatabaseMissing('service_products', [
            'service_id' => $serviceId,
        ]);
    }

    public function test_service_products_cannot_duplicate_product_ids(): void
    {
        $category = Category::query()->create([
            'name' => 'Spa',
            'is_active' => true,
        ]);

        $product = Product::query()->create([
            'name' => 'Oil',
            'category_id' => $category->id,
            'is_active' => true,
        ]);

        $this->actingAsSystemAdmin(['phone' => '+917777777781']);

        $this->postJson('/api/v1/services', [
            'name' => 'Oil Massage',
            'category_id' => $category->id,
            'default_price' => 500,
            'duration_minutes' => 60,
            'is_active' => true,
            'products' => [
                [
                    'product_id' => $product->id,
                    'default_price' => 100,
                ],
                [
                    'product_id' => $product->id,
                    'default_price' => 120,
                ],
            ],
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['products.1.product_id']);
    }

    public function test_deleting_service_removes_pivot_rows(): void
    {
        $category = Category::query()->create([
            'name' => 'Grooming',
            'is_active' => true,
        ]);

        $product = Product::query()->create([
            'name' => 'Gel',
            'category_id' => $category->id,
            'is_active' => true,
        ]);

        $service = Service::query()->create([
            'name' => 'Styling',
            'category_id' => $category->id,
            'default_price' => 350,
            'duration_minutes' => 40,
            'is_active' => true,
        ]);

        $service->serviceProducts()->create([
            'product_id' => $product->id,
            'default_price' => 25,
        ]);

        $this->actingAsSystemAdmin(['phone' => '+917777777782']);

        $this->deleteJson("/api/v1/services/{$service->id}")->assertOk();

        $this->assertDatabaseMissing('service_products', ['service_id' => $service->id]);
    }

    public function test_authenticated_user_can_search_and_paginate_services(): void
    {
        $category = Category::query()->create([
            'name' => 'Hair',
            'is_active' => true,
        ]);

        $this->actingAsOwner(['phone' => '+917333333333']);

        Service::query()->create([
            'name' => 'Hair Wash',
            'category_id' => $category->id,
            'default_price' => 299.50,
            'duration_minutes' => 30,
            'is_active' => true,
        ]);

        Service::query()->create([
            'name' => 'UniqueBeardTrimServiceSpecial',
            'category_id' => $category->id,
            'default_price' => 150,
            'duration_minutes' => 20,
            'is_active' => true,
        ]);

        $this->getJson('/api/v1/services?page=1&per_page=10&search=UniqueBeardTrimServiceSpecial')
            ->assertOk()
            ->assertJsonCount(1, 'data.services')
            ->assertJsonPath('data.services.0.name', 'UniqueBeardTrimServiceSpecial')
            ->assertJsonPath('data.meta.total', 1);

        $totalServices = Service::count();
        $this->getJson('/api/v1/services?per_page=1&page=2')
            ->assertOk()
            ->assertJsonCount(1, 'data.services')
            ->assertJsonPath('data.meta.current_page', 2)
            ->assertJsonPath('data.meta.per_page', 1)
            ->assertJsonPath('data.meta.total', $totalServices)
            ->assertJsonPath('data.meta.last_page', (int) ceil($totalServices / 1));
    }
}
