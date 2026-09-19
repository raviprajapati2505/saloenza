<?php

namespace Tests\Feature\Inventory;

use App\Models\BranchProductStock;
use App\Models\Category;
use App\Models\Product;
use App\Models\Saloon;
use App\Models\SaloonBranch;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class InventoryApiTest extends TestCase
{
    use RefreshDatabase;

    private Saloon $saloon;

    private User $owner;

    private SaloonBranch $branch;

    private Product $shampoo;

    protected function setUp(): void
    {
        parent::setUp();

        $this->owner = $this->actingAsOwner(['phone' => '+917000000070']);
        $this->saloon = Saloon::query()->findOrFail($this->owner->saloon_id);
        $this->assignDefaultSubscription($this->saloon, 'pro');

        $this->branch = SaloonBranch::query()->create([
            'saloon_id' => $this->saloon->id,
            'branch_name' => 'Inventory Branch',
            'business_address_1' => '123 Stock Street',
            'city' => 'Mumbai',
            'state' => 'Maharashtra',
            'area_pincode' => '400001',
            'country' => 'India',
            'is_active' => true,
        ]);

        $category = Category::query()->create(['name' => 'Hair', 'is_active' => true]);
        $this->shampoo = Product::query()->create([
            'name' => 'Professional Shampoo',
            'sku' => 'SHP-TEST',
            'brand' => 'GlowPro',
            'unit' => 'bottle',
            'category_id' => $category->id,
            'is_active' => true,
        ]);

        BranchProductStock::query()->create([
            'branch_id' => $this->branch->id,
            'product_id' => $this->shampoo->id,
            'quantity_on_hand' => 10,
            'reorder_level' => 3,
            'cost_price' => 200,
            'selling_price' => 450,
        ]);
    }

    public function test_owner_can_fetch_inventory_summary_and_stock(): void
    {
        Sanctum::actingAs($this->owner);

        $this->getJson("/api/v1/inventory/summary?branch_id={$this->branch->id}")
            ->assertOk()
            ->assertJsonPath('data.summary.total_skus', 1)
            ->assertJsonPath('data.summary.low_stock_count', 0);

        $this->getJson("/api/v1/inventory/stock?branch_id={$this->branch->id}&page=1&per_page=20")
            ->assertOk()
            ->assertJsonPath('data.stock.0.product.name', 'Professional Shampoo')
            ->assertJsonPath('data.stock.0.quantity_on_hand', 10)
            ->assertJsonPath('data.stock.0.stock_status', 'ok');
    }

    public function test_owner_can_adjust_stock_and_log_movement(): void
    {
        Sanctum::actingAs($this->owner);

        $stock = BranchProductStock::query()->firstOrFail();

        $this->postJson("/api/v1/inventory/stock/{$stock->id}/adjust", [
            'quantity_change' => -2,
            'notes' => 'Damaged units',
        ])
            ->assertOk()
            ->assertJsonPath('data.stock.quantity_on_hand', 8);

        $this->assertDatabaseHas('inventory_movements', [
            'branch_id' => $this->branch->id,
            'product_id' => $this->shampoo->id,
            'quantity_change' => -2,
            'quantity_after' => 8,
            'type' => 'adjustment',
        ]);
    }

    public function test_owner_can_create_supplier_and_restock_via_purchase_order(): void
    {
        Sanctum::actingAs($this->owner);

        $supplierResponse = $this->postJson('/api/v1/suppliers', [
            'name' => 'Beauty Wholesale Co.',
            'phone' => '+919876543210',
            'is_active' => true,
        ])->assertCreated();

        $supplierId = $supplierResponse->json('data.supplier.id');

        $this->postJson('/api/v1/purchase-orders', [
            'branch_id' => $this->branch->id,
            'supplier_id' => $supplierId,
            'place_order' => true,
            'receive_now' => true,
            'items' => [
                [
                    'product_id' => $this->shampoo->id,
                    'quantity_ordered' => 5,
                    'unit_cost' => 210,
                ],
            ],
        ])
            ->assertCreated()
            ->assertJsonPath('data.purchase_order.status', 'received');

        $this->assertDatabaseHas('branch_product_stocks', [
            'branch_id' => $this->branch->id,
            'product_id' => $this->shampoo->id,
            'quantity_on_hand' => 15,
            'supplier_id' => $supplierId,
        ]);

        $this->assertDatabaseHas('inventory_movements', [
            'branch_id' => $this->branch->id,
            'product_id' => $this->shampoo->id,
            'quantity_change' => 5,
            'type' => 'purchase',
        ]);
    }

    public function test_inventory_requires_subscription_module(): void
    {
        $this->assignDefaultSubscription($this->saloon, 'free');
        Sanctum::actingAs($this->owner);

        $this->getJson("/api/v1/inventory/summary?branch_id={$this->branch->id}")
            ->assertForbidden();
    }

    public function test_basic_plan_includes_inventory_and_owner_can_access(): void
    {
        $this->seed(\Database\Seeders\SubscriptionPlanSeeder::class);
        $this->assignDefaultSubscription($this->saloon, 'basic');
        Sanctum::actingAs($this->owner);

        $basic = \App\Models\SubscriptionPlan::query()->where('slug', 'basic')->firstOrFail();
        $this->assertContains(\App\Support\Subscription\SubscriptionModules::INVENTORY, $basic->moduleList());

        $this->getJson('/api/v1/me')
            ->assertOk()
            ->assertJsonPath('data.tenant.plan.slug', 'basic');

        $modules = $this->getJson('/api/v1/me')->json('data.subscription_modules');
        $this->assertContains('inventory', $modules);

        $this->getJson("/api/v1/inventory/summary?branch_id={$this->branch->id}")
            ->assertOk();
    }
}
