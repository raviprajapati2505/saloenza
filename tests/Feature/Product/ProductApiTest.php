<?php

namespace Tests\Feature\Product;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_read_products_and_admin_can_manage_them(): void
    {
        $this->actingAsOwner(['phone' => '+917777777778']);

        $initialCount = Product::count();
        $this->getJson('/api/v1/products?page=1&per_page=10')
            ->assertOk()
            ->assertJsonCount(min(10, $initialCount), 'data.products');

        $admin = $this->createSystemAdmin(['phone' => '+917777777779']);
        $this->actingAs($admin);

        $category = Category::query()->create([
            'name' => 'Hair',
            'is_active' => true,
        ]);

        $createResponse = $this->postJson('/api/v1/products', [
            'name' => 'Shampoo',
            'category_id' => $category->id,
            'is_active' => true,
        ]);

        $createResponse
            ->assertCreated()
            ->assertJsonPath('data.product.name', 'Shampoo')
            ->assertJsonPath('data.product.category_id', $category->id)
            ->assertJsonPath('data.product.is_active', true);

        $productId = (int) $createResponse->json('data.product.id');

        $this->getJson('/api/v1/products?page=1&per_page=10')
            ->assertOk()
            ->assertJsonCount(min(10, $initialCount + 1), 'data.products');

        $this->getJson("/api/v1/products/{$productId}")
            ->assertOk()
            ->assertJsonPath('data.product.name', 'Shampoo');

        $this->putJson("/api/v1/products/{$productId}", [
            'name' => 'Conditioner',
            'category_id' => $category->id,
            'is_active' => false,
        ])->assertOk()
            ->assertJsonPath('data.product.name', 'Conditioner')
            ->assertJsonPath('data.product.is_active', false);

        $this->deleteJson("/api/v1/products/{$productId}")
            ->assertOk()
            ->assertJsonPath('message', 'Product deleted successfully.');

        $this->assertDatabaseMissing('products', [
            'id' => $productId,
        ]);
    }

    public function test_product_name_must_be_unique(): void
    {
        $category = Category::query()->create([
            'name' => 'Hair',
            'is_active' => true,
        ]);

        Product::query()->create([
            'name' => 'Hair Color',
            'category_id' => $category->id,
            'is_active' => true,
        ]);

        $this->actingAsSystemAdmin(['phone' => '+917777777780']);

        $this->postJson('/api/v1/products', [
            'name' => 'Hair Color',
            'category_id' => $category->id,
            'is_active' => true,
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['name']);
    }

    public function test_authenticated_user_can_search_and_paginate_products(): void
    {
        $category = Category::query()->create([
            'name' => 'Hair',
            'is_active' => true,
        ]);

        $this->actingAsOwner(['phone' => '+917444444444']);

        Product::query()->create([
            'name' => 'UniqueShampooProduct',
            'category_id' => $category->id,
            'is_active' => true,
        ]);

        Product::query()->create([
            'name' => 'UniqueConditionerProduct',
            'category_id' => $category->id,
            'is_active' => false,
        ]);

        $this->getJson('/api/v1/products?page=1&per_page=10&search=UniqueShampooProduct')
            ->assertOk()
            ->assertJsonCount(1, 'data.products')
            ->assertJsonPath('data.products.0.name', 'UniqueShampooProduct')
            ->assertJsonPath('data.meta.total', 1);

        $this->getJson('/api/v1/products?page=1&per_page=10&search=UniqueConditionerProduct&is_active=0')
            ->assertOk()
            ->assertJsonCount(1, 'data.products')
            ->assertJsonPath('data.products.0.name', 'UniqueConditionerProduct');

        $total = Product::count();
        $this->getJson('/api/v1/products?per_page=1&page=2')
            ->assertOk()
            ->assertJsonCount(1, 'data.products')
            ->assertJsonPath('data.meta.current_page', 2)
            ->assertJsonPath('data.meta.per_page', 1)
            ->assertJsonPath('data.meta.total', $total)
            ->assertJsonPath('data.meta.last_page', (int) ceil($total / 1));
    }
}
