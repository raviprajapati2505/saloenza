<?php

namespace Tests\Feature\Pos;

use App\Models\Appointment;
use App\Models\BranchProductStock;
use App\Models\Category;
use App\Models\Product;
use App\Models\SalonServiceProduct;
use App\Models\Saloon;
use App\Models\SaloonBranch;
use App\Models\Service;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ProductSaleApiTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private Saloon $saloon;

    private SaloonBranch $branch;

    private Service $haircut;

    private Product $serum;

    public function test_stocked_product_is_sellable_without_a_service_catalog_entry(): void
    {
        Sanctum::actingAs($this->owner);

        $this->getJson('/api/v1/pos/catalog?branch_id='.$this->branch->id)
            ->assertOk()
            ->assertJsonPath('data.catalog.retail_products.0.product_id', $this->serum->id)
            ->assertJsonPath('data.catalog.retail_products.0.price', 450)
            ->assertJsonPath('data.catalog.retail_products.0.stock_on_hand', 10);
    }

    public function test_catalog_falls_back_to_the_actors_home_branch(): void
    {
        $this->owner->forceFill(['branch_id' => $this->branch->id])->save();
        Sanctum::actingAs($this->owner->fresh());

        // No branch_id on the request: the inventory screens resolve one, so must the counter.
        $this->getJson('/api/v1/pos/catalog')
            ->assertOk()
            ->assertJsonPath('data.catalog.branch_id', $this->branch->id)
            ->assertJsonCount(1, 'data.catalog.retail_products')
            ->assertJsonPath('data.catalog.retail_products.0.product_id', $this->serum->id);
    }

    public function test_product_only_checkout_creates_a_counter_sale(): void
    {
        Sanctum::actingAs($this->owner);

        $response = $this->postJson('/api/v1/pos/checkout', [
            'branch_id' => $this->branch->id,
            'customer_name' => 'Retail Walk-in',
            'status' => 'completed',
            'collect_payment' => true,
            'payment_method' => 'cash',
            'amount_paid' => 900,
            'items' => [[
                'kind' => 'retail',
                'product_id' => $this->serum->id,
                'quantity' => 2,
            ]],
        ]);

        $response
            ->assertCreated()
            ->assertJsonPath('data.appointment.type', Appointment::TYPE_PRODUCT_SALE)
            ->assertJsonPath('data.appointment.services_total', '0.00')
            ->assertJsonPath('data.appointment.products_total', '900.00')
            ->assertJsonPath('data.appointment.grand_total', '900.00')
            ->assertJsonPath('data.appointment.payment_status', 'paid')
            ->assertJsonCount(1, 'data.appointment.products');

        $appointmentId = $response->json('data.appointment.id');

        $this->assertDatabaseHas('appointment_products', [
            'appointment_id' => $appointmentId,
            'product_id' => $this->serum->id,
            'quantity' => 2,
            'unit_price' => 450,
            'unit_cost' => 200,
            'line_total' => 900,
        ]);

        $this->assertDatabaseHas('branch_product_stocks', [
            'branch_id' => $this->branch->id,
            'product_id' => $this->serum->id,
            'quantity_on_hand' => 8,
        ]);

        $this->assertDatabaseCount('appointment_services', 0);
    }

    public function test_checkout_mixing_service_and_product_splits_the_totals(): void
    {
        Sanctum::actingAs($this->owner);

        $this->postJson('/api/v1/pos/checkout', [
            'branch_id' => $this->branch->id,
            'customer_name' => 'Mixed Cart',
            'status' => 'completed',
            'items' => [
                ['kind' => 'service', 'service_id' => $this->haircut->id, 'quantity' => 1],
                ['kind' => 'retail', 'product_id' => $this->serum->id, 'quantity' => 1],
            ],
        ])
            ->assertCreated()
            ->assertJsonPath('data.appointment.type', Appointment::TYPE_WALK_IN)
            ->assertJsonPath('data.appointment.services_total', '500.00')
            ->assertJsonPath('data.appointment.products_total', '450.00')
            ->assertJsonPath('data.appointment.grand_total', '950.00');
    }

    public function test_service_variant_does_not_require_retail_stock(): void
    {
        Sanctum::actingAs($this->owner);

        $color = Product::query()->create([
            'name' => 'Ash Blonde Colour',
            'category_id' => $this->serum->category_id,
            'is_active' => true,
        ]);

        SalonServiceProduct::query()->create([
            'saloon_id' => $this->saloon->id,
            'branch_id' => null,
            'service_id' => $this->haircut->id,
            'product_id' => $color->id,
            'price' => 1200,
            'duration_minutes' => 45,
            'is_active' => true,
        ]);

        $this->postJson('/api/v1/pos/checkout', [
            'branch_id' => $this->branch->id,
            'customer_name' => 'Colour Client',
            'items' => [[
                'kind' => 'service',
                'service_id' => $this->haircut->id,
                'product_id' => $color->id,
                'price' => 1200,
                'duration_minutes' => 45,
            ]],
        ])
            ->assertCreated()
            ->assertJsonPath('data.appointment.status', 'completed')
            ->assertJsonPath('data.appointment.grand_total', '1200.00');

        $this->assertDatabaseMissing('branch_product_stocks', [
            'branch_id' => $this->branch->id,
            'product_id' => $color->id,
        ]);

        $this->postJson('/api/v1/appointments', [
            'type' => Appointment::TYPE_WALK_IN,
            'starts_at' => now()->toIso8601String(),
            'customer_name' => 'Walk-in Colour',
            'branch_id' => $this->branch->id,
            'services' => [[
                'service_id' => $this->haircut->id,
                'product_id' => $color->id,
                'price' => 1200,
                'duration_minutes' => 45,
            ]],
        ])
            ->assertCreated()
            ->assertJsonPath('data.appointment.status', 'completed');
    }

    public function test_products_can_be_added_to_a_booked_appointment(): void
    {
        Sanctum::actingAs($this->owner);

        $starts = now()->addDay()->setTime(10, 0);

        $response = $this->postJson('/api/v1/appointments', [
            'type' => Appointment::TYPE_APPOINTMENT,
            'starts_at' => $starts->toIso8601String(),
            'status' => 'scheduled',
            'customer_name' => 'Booked Customer',
            'branch_id' => $this->branch->id,
            'services' => [[
                'service_id' => $this->haircut->id,
                'price' => 500,
                'duration_minutes' => 30,
            ]],
            'products' => [[
                'product_id' => $this->serum->id,
                'quantity' => 1,
            ]],
        ]);

        $response
            ->assertCreated()
            ->assertJsonPath('data.appointment.products_total', '450.00')
            ->assertJsonPath('data.appointment.grand_total', '950.00');

        // Stock stays put until the visit is completed.
        $this->assertDatabaseHas('branch_product_stocks', [
            'branch_id' => $this->branch->id,
            'product_id' => $this->serum->id,
            'quantity_on_hand' => 10,
        ]);

        $appointmentId = $response->json('data.appointment.id');

        $this->putJson("/api/v1/appointments/{$appointmentId}", [
            'starts_at' => $starts->toIso8601String(),
            'status' => 'completed',
            'branch_id' => $this->branch->id,
            'services' => [[
                'service_id' => $this->haircut->id,
                'price' => 500,
                'duration_minutes' => 30,
            ]],
            'products' => [[
                'product_id' => $this->serum->id,
                'quantity' => 1,
            ]],
        ])->assertOk();

        $this->assertDatabaseHas('branch_product_stocks', [
            'branch_id' => $this->branch->id,
            'product_id' => $this->serum->id,
            'quantity_on_hand' => 9,
        ]);
    }

    public function test_checkout_rejects_a_product_without_a_retail_price(): void
    {
        Sanctum::actingAs($this->owner);

        $unpriced = Product::query()->create([
            'name' => 'Backbar Bleach',
            'category_id' => $this->serum->category_id,
            'is_active' => true,
        ]);

        BranchProductStock::query()->create([
            'branch_id' => $this->branch->id,
            'product_id' => $unpriced->id,
            'quantity_on_hand' => 5,
            'reorder_level' => 1,
        ]);

        $this->postJson('/api/v1/pos/checkout', [
            'branch_id' => $this->branch->id,
            'customer_name' => 'No Price',
            'status' => 'completed',
            'items' => [[
                'kind' => 'retail',
                'product_id' => $unpriced->id,
                'quantity' => 1,
            ]],
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['products.0.product_id']);
    }

    public function test_product_sales_report_splits_counter_and_attached_revenue(): void
    {
        Sanctum::actingAs($this->owner);

        $this->postJson('/api/v1/pos/checkout', [
            'branch_id' => $this->branch->id,
            'customer_name' => 'Counter Buyer',
            'status' => 'completed',
            'items' => [[
                'kind' => 'retail',
                'product_id' => $this->serum->id,
                'quantity' => 2,
            ]],
        ])->assertCreated();

        $this->postJson('/api/v1/pos/checkout', [
            'branch_id' => $this->branch->id,
            'customer_name' => 'Service Buyer',
            'status' => 'completed',
            'items' => [
                ['kind' => 'service', 'service_id' => $this->haircut->id, 'quantity' => 1],
                ['kind' => 'retail', 'product_id' => $this->serum->id, 'quantity' => 1],
            ],
        ])->assertCreated();

        $today = now()->toDateString();

        $this->getJson("/api/v1/inventory/product-sales?from={$today}&to={$today}")
            ->assertOk()
            ->assertJsonPath('data.summary.units_sold', 3)
            ->assertJsonPath('data.summary.transactions', 2)
            ->assertJsonPath('data.summary.gross_revenue', 1350)
            ->assertJsonPath('data.summary.cost_of_goods', 600)
            ->assertJsonPath('data.summary.gross_margin', 750)
            ->assertJsonPath('data.summary.counter_sale_revenue', 900)
            ->assertJsonPath('data.summary.attached_to_service_revenue', 450)
            ->assertJsonPath('data.summary.top_products.0.product_id', $this->serum->id)
            ->assertJsonPath('data.summary.top_products.0.units', 3);
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->owner = $this->actingAsOwner(['phone' => '+917000000070']);
        $this->saloon = Saloon::query()->findOrFail($this->owner->saloon_id);
        $this->assignDefaultSubscription($this->saloon, 'pro');

        $this->branch = SaloonBranch::query()->create([
            'saloon_id' => $this->saloon->id,
            'branch_name' => 'Retail Branch',
            'business_address_1' => '9 Retail Road',
            'city' => 'Mumbai',
            'state' => 'Maharashtra',
            'area_pincode' => '400001',
            'country' => 'India',
            'is_active' => true,
        ]);

        $category = Category::query()->create(['name' => 'Hair', 'is_active' => true]);

        $this->haircut = Service::query()->create([
            'name' => 'Haircut',
            'category_id' => $category->id,
            'default_price' => 500,
            'duration_minutes' => 30,
            'is_active' => true,
        ]);

        SalonServiceProduct::query()->create([
            'saloon_id' => $this->saloon->id,
            'branch_id' => null,
            'service_id' => $this->haircut->id,
            'product_id' => null,
            'price' => 500,
            'duration_minutes' => 30,
            'is_active' => true,
        ]);

        $this->serum = Product::query()->create([
            'name' => 'Repair Serum',
            'category_id' => $category->id,
            'is_active' => true,
        ]);

        // Deliberately no SalonServiceProduct row: branch stock alone makes it sellable.
        BranchProductStock::query()->create([
            'branch_id' => $this->branch->id,
            'product_id' => $this->serum->id,
            'quantity_on_hand' => 10,
            'reorder_level' => 2,
            'cost_price' => 200,
            'selling_price' => 450,
        ]);
    }
}
