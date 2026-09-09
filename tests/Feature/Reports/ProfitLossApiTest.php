<?php

namespace Tests\Feature\Reports;

use App\Models\BranchProductStock;
use App\Models\Category;
use App\Models\Expense;
use App\Models\Product;
use App\Models\Role;
use App\Models\SalonServiceProduct;
use App\Models\Saloon;
use App\Models\SaloonBranch;
use App\Models\Service;
use App\Models\User;
use App\Support\Role\RoleCodes;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ProfitLossApiTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private Saloon $saloon;

    private SaloonBranch $branch;

    private Service $haircut;

    private Product $serum;

    private User $stylist;

    private Product $consumable;

    public function test_owner_can_record_and_list_an_expense(): void
    {
        Sanctum::actingAs($this->owner);

        $this->postJson('/api/v1/expenses', [
            'category' => 'rent',
            'title' => 'August shop rent',
            'amount' => 25000,
            'incurred_on' => now()->toDateString(),
            'branch_id' => $this->branch->id,
        ])
            ->assertCreated()
            ->assertJsonPath('data.expense.category', 'rent')
            ->assertJsonPath('data.expense.category_label', 'Rent & Lease')
            ->assertJsonPath('data.expense.amount', '25000.00');

        $this->getJson('/api/v1/expenses')
            ->assertOk()
            ->assertJsonCount(1, 'data.expenses')
            ->assertJsonPath('data.expenses.0.title', 'August shop rent');
    }

    public function test_expense_requires_a_known_category_and_positive_amount(): void
    {
        Sanctum::actingAs($this->owner);

        $this->postJson('/api/v1/expenses', [
            'category' => 'yacht',
            'title' => 'Not a salon cost',
            'amount' => 0,
            'incurred_on' => now()->toDateString(),
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['category', 'amount']);
    }

    public function test_profit_and_loss_subtracts_goods_payroll_and_overheads(): void
    {
        Sanctum::actingAs($this->owner);

        // Haircut 500 (consumable cost 80) + 2 serums at 450 (cost 200 each).
        $this->postJson('/api/v1/pos/checkout', [
            'branch_id' => $this->branch->id,
            'customer_name' => 'Full Basket',
            'status' => 'completed',
            'items' => [
                [
                    'kind' => 'service',
                    'service_id' => $this->haircut->id,
                    'product_id' => $this->consumable->id,
                    'quantity' => 1,
                    'staff_id' => $this->stylist->id,
                ],
                ['kind' => 'retail', 'product_id' => $this->serum->id, 'quantity' => 2, 'staff_id' => $this->stylist->id],
            ],
        ])->assertCreated();

        Expense::query()->create([
            'saloon_id' => $this->saloon->id,
            'branch_id' => $this->branch->id,
            'category' => 'rent',
            'title' => 'Rent',
            'amount' => 300,
            'incurred_on' => now()->toDateString(),
        ]);

        $today = now()->toDateString();

        $response = $this->getJson("/api/v1/reports/profit-loss?from={$today}&to={$today}")
            ->assertOk();

        $summary = $response->json('data.summary');

        $this->assertEquals(500, $summary['revenue']['services']);
        $this->assertEquals(900, $summary['revenue']['products']);
        $this->assertEquals(1400, $summary['revenue']['net']);

        // 2 serums at cost 200 = 400 retail, plus one 80 consumable burned by the haircut.
        $this->assertEquals(400, $summary['cost_of_goods']['retail_products']);
        $this->assertEquals(80, $summary['cost_of_goods']['service_consumables']);
        $this->assertEquals(920, $summary['gross_profit']);

        // Stylist earns 10% on the 1400 they generated, salary is 3000/30 for one day.
        $this->assertEquals(140, $summary['staff_cost']['commission']);
        $this->assertEquals(100, $summary['staff_cost']['salaries']);
        $this->assertEquals(300, $summary['operating_expenses']['total']);

        $this->assertEquals(380, $summary['net_profit']);
        $this->assertEquals(27.1, $summary['net_margin_percent']);
    }

    public function test_recorded_salary_expenses_replace_the_payroll_estimate(): void
    {
        Sanctum::actingAs($this->owner);

        Expense::query()->create([
            'saloon_id' => $this->saloon->id,
            'branch_id' => $this->branch->id,
            'category' => 'salaries',
            'title' => 'August payroll',
            'amount' => 4000,
            'incurred_on' => now()->toDateString(),
        ]);

        $today = now()->toDateString();

        $summary = $this->getJson("/api/v1/reports/profit-loss?from={$today}&to={$today}")
            ->assertOk()
            ->json('data.summary');

        $this->assertTrue($summary['staff_cost']['salaries_from_expenses']);
        $this->assertEquals(0, $summary['staff_cost']['salaries']);
        $this->assertEquals(4000, $summary['operating_expenses']['total']);
    }

    public function test_salon_wide_overheads_are_reported_separately_from_a_branch_view(): void
    {
        Sanctum::actingAs($this->owner);

        Expense::query()->create([
            'saloon_id' => $this->saloon->id,
            'branch_id' => null,
            'category' => 'software',
            'title' => 'Booking software',
            'amount' => 1200,
            'incurred_on' => now()->toDateString(),
        ]);

        $today = now()->toDateString();

        $summary = $this->getJson(
            "/api/v1/reports/profit-loss?from={$today}&to={$today}&branch_id={$this->branch->id}",
        )
            ->assertOk()
            ->json('data.summary');

        $this->assertEquals(0, $summary['operating_expenses']['total']);
        $this->assertEquals(1200, $summary['operating_expenses']['unallocated_overheads']);
    }

    public function test_uncosted_product_lines_are_flagged(): void
    {
        Sanctum::actingAs($this->owner);

        BranchProductStock::query()
            ->where('branch_id', $this->branch->id)
            ->where('product_id', $this->serum->id)
            ->update(['cost_price' => null]);

        $this->postJson('/api/v1/pos/checkout', [
            'branch_id' => $this->branch->id,
            'customer_name' => 'Uncosted',
            'status' => 'completed',
            'items' => [['kind' => 'retail', 'product_id' => $this->serum->id, 'quantity' => 1]],
        ])->assertCreated();

        $today = now()->toDateString();

        $summary = $this->getJson("/api/v1/reports/profit-loss?from={$today}&to={$today}")
            ->assertOk()
            ->json('data.summary');

        $this->assertEquals(0, $summary['cost_of_goods']['retail_products']);
        $this->assertSame(1, $summary['cost_of_goods']['uncosted_lines']);
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->owner = $this->actingAsOwner(['phone' => '+917000000090']);
        $this->saloon = Saloon::query()->findOrFail($this->owner->saloon_id);
        $this->assignDefaultSubscription($this->saloon, 'pro');

        $this->branch = SaloonBranch::query()->create([
            'saloon_id' => $this->saloon->id,
            'branch_name' => 'Profit Branch',
            'business_address_1' => '4 Ledger Lane',
            'city' => 'Mumbai',
            'state' => 'Maharashtra',
            'area_pincode' => '400001',
            'country' => 'India',
            'is_active' => true,
        ]);

        $this->stylist = User::factory()->create([
            'name' => 'Priya Stylist',
            'phone' => '+917000000091',
            'saloon_id' => $this->saloon->id,
            'branch_id' => $this->branch->id,
            'role_id' => Role::findByCode(RoleCodes::SALON_STAFF)->id,
            'is_active' => true,
            'commission_rate' => 10,
            'per_month_salary' => 3000,
            'joined_at' => now()->subYear(),
        ]);

        $category = Category::query()->create(['name' => 'Hair', 'is_active' => true]);

        $consumable = Product::query()->create([
            'name' => 'Shampoo Sachet',
            'category_id' => $category->id,
            'is_active' => true,
        ]);

        BranchProductStock::query()->create([
            'branch_id' => $this->branch->id,
            'product_id' => $consumable->id,
            'quantity_on_hand' => 50,
            'reorder_level' => 5,
            'cost_price' => 80,
        ]);

        $this->haircut = Service::query()->create([
            'name' => 'Haircut',
            'category_id' => $category->id,
            'default_price' => 500,
            'duration_minutes' => 30,
            'is_active' => true,
        ]);

        // Base offering plus the variant that burns a consumable during the service.
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
            'service_id' => $this->haircut->id,
            'product_id' => $consumable->id,
            'price' => 500,
            'duration_minutes' => 30,
            'is_active' => true,
        ]);

        $this->consumable = $consumable;

        $this->serum = Product::query()->create([
            'name' => 'Repair Serum',
            'category_id' => $category->id,
            'is_active' => true,
        ]);

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
