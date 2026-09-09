<?php

namespace Tests\Feature\Category;

use App\Models\Category;
use App\Models\Product;
use App\Models\Service;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CategoryApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_read_categories_and_admin_can_manage_them(): void
    {
        $this->actingAsOwner(['phone' => '+917777777777']);

        $initialCount = Category::count();
        $this->getJson('/api/v1/categories?page=1&per_page=10')
            ->assertOk()
            ->assertJsonCount(min(10, $initialCount), 'data.categories');

        $admin = $this->createSystemAdmin(['phone' => '+917777777778']);
        $this->actingAs($admin);

        $createResponse = $this->postJson('/api/v1/categories', [
            'name' => 'Hair Care',
            'is_active' => true,
        ]);

        $createResponse
            ->assertCreated()
            ->assertJsonPath('data.category.name', 'Hair Care')
            ->assertJsonPath('data.category.is_active', true);

        $categoryId = (int) $createResponse->json('data.category.id');

        $this->getJson('/api/v1/categories?page=1&per_page=10')
            ->assertOk()
            ->assertJsonCount(min(10, $initialCount + 1), 'data.categories');

        $this->getJson("/api/v1/categories/{$categoryId}")
            ->assertOk()
            ->assertJsonPath('data.category.name', 'Hair Care');

        $this->putJson("/api/v1/categories/{$categoryId}", [
            'name' => 'Skin Care',
            'is_active' => false,
        ])->assertOk()
            ->assertJsonPath('data.category.name', 'Skin Care')
            ->assertJsonPath('data.category.is_active', false);

        $this->deleteJson("/api/v1/categories/{$categoryId}")
            ->assertOk()
            ->assertJsonPath('message', 'Category deleted successfully.');

        $this->assertSoftDeleted('categories', [
            'id' => $categoryId,
        ]);
    }

    public function test_soft_deleted_category_is_hidden_from_listing_and_show_route(): void
    {
        $this->actingAsOwner(['phone' => '+916666666666']);

        $category = Category::query()->create([
            'name' => 'Nail Care',
            'is_active' => true,
        ]);

        $category->delete();

        $this->getJson('/api/v1/categories?page=1&per_page=10')
            ->assertOk()
            ->assertJsonCount(min(10, Category::count()), 'data.categories');

        $this->getJson("/api/v1/categories/{$category->id}")
            ->assertNotFound();
    }

    public function test_authenticated_user_can_search_and_paginate_categories(): void
    {
        $this->actingAsOwner(['phone' => '+915555555555']);

        Category::query()->create([
            'name' => 'UniqueHairCategorySpecial',
            'is_active' => true,
        ]);

        Category::query()->create([
            'name' => 'Skin Care',
            'is_active' => false,
        ]);

        $this->getJson('/api/v1/categories?page=1&per_page=10&search=UniqueHairCategorySpecial')
            ->assertOk()
            ->assertJsonCount(1, 'data.categories')
            ->assertJsonPath('data.categories.0.name', 'UniqueHairCategorySpecial')
            ->assertJsonPath('data.meta.total', 1);

        $this->getJson('/api/v1/categories?page=1&per_page=10&is_active=0')
            ->assertOk()
            ->assertJsonCount(1, 'data.categories')
            ->assertJsonPath('data.categories.0.name', 'Skin Care');

        $total = Category::count();
        $this->getJson('/api/v1/categories?page=2&per_page=1')
            ->assertOk()
            ->assertJsonCount(1, 'data.categories')
            ->assertJsonPath('data.meta.current_page', 2)
            ->assertJsonPath('data.meta.per_page', 1)
            ->assertJsonPath('data.meta.total', $total)
            ->assertJsonPath('data.meta.last_page', (int) ceil($total / 1));
    }

    public function test_category_cannot_be_deleted_when_linked_to_catalog_items(): void
    {
        $this->actingAsSystemAdmin(['phone' => '+917777777790']);

        $category = Category::query()->create([
            'name' => 'Linked Category',
            'is_active' => true,
        ]);

        Product::query()->create([
            'name' => 'Linked Product',
            'category_id' => $category->id,
            'is_active' => true,
        ]);

        $this->deleteJson("/api/v1/categories/{$category->id}")
            ->assertStatus(422)
            ->assertJsonPath('message', 'Cannot delete a category that is linked to services or products.');

        Service::query()->create([
            'name' => 'Linked Service',
            'category_id' => $category->id,
            'default_price' => 100,
            'duration_minutes' => 30,
            'is_active' => true,
        ]);

        Product::query()->where('category_id', $category->id)->delete();

        $this->deleteJson("/api/v1/categories/{$category->id}")
            ->assertStatus(422);
    }
}
