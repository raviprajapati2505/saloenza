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
use App\Support\Pos\PosCatalog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PosCheckoutApiTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private Saloon $saloon;

    private SaloonBranch $branch;

    private Service $haircut;

    private Service $retailService;

    private Product $shampoo;

    public function test_pos_checkout_creates_sale_and_deducts_stock(): void
    {
        Sanctum::actingAs($this->owner);

        $response = $this->postJson('/api/v1/pos/checkout', [
            'branch_id' => $this->branch->id,
            'customer_name' => 'POS Customer',
            'discount' => 0,
            'status' => 'completed',
            'collect_payment' => true,
            'payment_method' => 'cash',
            'amount_paid' => 1099,
            'items' => [
                [
                    'kind' => 'service',
                    'service_id' => $this->haircut->id,
                    'quantity' => 1,
                    'price' => 500,
                    'duration_minutes' => 30,
                ],
                [
                    'kind' => 'retail',
                    'product_id' => $this->shampoo->id,
                    'quantity' => 2,
                    'price' => 299.5,
                ],
            ],
        ]);

        $response
            ->assertCreated()
            ->assertJsonPath('data.appointment.type', Appointment::TYPE_WALK_IN)
            ->assertJsonPath('data.appointment.status', 'completed')
            ->assertJsonPath('data.appointment.payment_status', 'paid');

        $this->assertDatabaseHas('branch_product_stocks', [
            'branch_id' => $this->branch->id,
            'product_id' => $this->shampoo->id,
            'quantity_on_hand' => 8,
        ]);
    }

    public function test_pos_checkout_defaults_to_completed_with_pending_payment(): void
    {
        Sanctum::actingAs($this->owner);

        $this->postJson('/api/v1/pos/checkout', [
            'branch_id' => $this->branch->id,
            'customer_name' => 'Pending POS Customer',
            'items' => [
                [
                    'kind' => 'service',
                    'service_id' => $this->haircut->id,
                    'quantity' => 1,
                    'price' => 500,
                    'duration_minutes' => 30,
                ],
            ],
        ])
            ->assertCreated()
            ->assertJsonPath('data.appointment.status', 'completed')
            ->assertJsonPath('data.appointment.payment_status', 'unpaid')
            ->assertJsonPath('data.appointment.balance_due', 500);
    }

    public function test_pos_checkout_rejects_insufficient_stock(): void
    {
        Sanctum::actingAs($this->owner);

        $this->postJson('/api/v1/pos/checkout', [
            'branch_id' => $this->branch->id,
            'customer_name' => 'POS Customer',
            'collect_payment' => false,
            'items' => [
                [
                    'kind' => 'retail',
                    'product_id' => $this->shampoo->id,
                    'quantity' => 99,
                    'price' => 299,
                ],
            ],
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['items.0.quantity']);
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->owner = $this->actingAsOwner(['phone' => '+917000000060']);
        $this->saloon = Saloon::query()->findOrFail($this->owner->saloon_id);

        $this->branch = SaloonBranch::query()->create([
            'saloon_id' => $this->saloon->id,
            'branch_name' => 'POS Branch',
            'business_address_1' => '123 POS Street',
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

        $retailCategory = Category::query()->create(['name' => 'Retail', 'is_active' => true]);
        $this->retailService = Service::query()->create([
            'name' => PosCatalog::RETAIL_SERVICE_NAME,
            'category_id' => $retailCategory->id,
            'default_price' => 0,
            'duration_minutes' => PosCatalog::RETAIL_DURATION_MINUTES,
            'is_active' => true,
        ]);

        $this->shampoo = Product::query()->create([
            'name' => 'Professional Shampoo',
            'category_id' => $category->id,
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

        SalonServiceProduct::query()->create([
            'saloon_id' => $this->saloon->id,
            'branch_id' => null,
            'service_id' => $this->retailService->id,
            'product_id' => $this->shampoo->id,
            'price' => 299,
            'duration_minutes' => PosCatalog::RETAIL_DURATION_MINUTES,
            'is_active' => true,
        ]);

        BranchProductStock::query()->create([
            'branch_id' => $this->branch->id,
            'product_id' => $this->shampoo->id,
            'quantity_on_hand' => 10,
            'reorder_level' => 3,
        ]);
    }
}
