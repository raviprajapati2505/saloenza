<?php

namespace Tests\Feature\Analytics;

use App\Models\Appointment;
use App\Models\Category;
use App\Models\Customer;
use App\Models\Saloon;
use App\Models\SaloonBranch;
use App\Models\Service;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SalonAnalyticsApiTest extends TestCase
{
    use RefreshDatabase;

    private Saloon $saloon;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();

        $this->owner = $this->actingAsOwner(['phone' => '+917000000051']);
        $this->saloon = Saloon::query()->findOrFail($this->owner->saloon_id);
        $this->assignDefaultSubscription($this->saloon, 'pro');
    }

    public function test_owner_can_fetch_analytics_summary_with_collected_revenue(): void
    {
        Sanctum::actingAs($this->owner);

        $branch = SaloonBranch::query()->create([
            'saloon_id' => $this->saloon->id,
            'branch_name' => 'Analytics Branch',
            'business_address_1' => '123 Test Street',
            'city' => 'Mumbai',
            'state' => 'Maharashtra',
            'area_pincode' => '400001',
            'country' => 'India',
            'is_active' => true,
        ]);

        $customer = Customer::query()->create([
            'name' => 'Analytics Customer',
            'phone' => '+919888888888',
            'is_active' => true,
        ]);
        $customer->attachSaloon($this->saloon->id);

        $category = Category::query()->create(['name' => 'Spa', 'is_active' => true]);
        $service = Service::query()->create([
            'name' => 'Spa',
            'category_id' => $category->id,
            'default_price' => 800,
            'duration_minutes' => 60,
            'is_active' => true,
        ]);

        $day = now()->subDay()->setTime(14, 0);

        Appointment::query()->create([
            'saloon_id' => $this->saloon->id,
            'branch_id' => $branch->id,
            'customer_id' => $customer->id,
            'service_id' => $service->id,
            'starts_at' => $day,
            'ends_at' => $day->copy()->addHour(),
            'status' => 'completed',
            'type' => Appointment::TYPE_APPOINTMENT,
            'price' => 800,
            'discount' => 0,
            'grand_total' => 800,
            'payment_status' => 'paid',
            'payment_method' => 'upi',
            'amount_paid' => 800,
            'paid_at' => $day,
            'created_by' => $this->owner->id,
        ]);

        Appointment::query()->create([
            'saloon_id' => $this->saloon->id,
            'branch_id' => $branch->id,
            'customer_id' => $customer->id,
            'service_id' => $service->id,
            'starts_at' => now()->subDay()->setTime(16, 0),
            'ends_at' => now()->subDay()->setTime(17, 0),
            'status' => 'completed',
            'type' => Appointment::TYPE_APPOINTMENT,
            'price' => 500,
            'discount' => 0,
            'grand_total' => 500,
            'payment_status' => 'unpaid',
            'amount_paid' => 0,
            'created_by' => $this->owner->id,
        ]);

        $from = now()->subDays(7)->toDateString();
        $to = now()->toDateString();

        $this->getJson("/api/v1/analytics/summary?from={$from}&to={$to}")
            ->assertOk()
            ->assertJsonPath('data.summary.collected_revenue', 800)
            ->assertJsonPath('data.summary.outstanding_revenue', 500)
            ->assertJsonPath('data.summary.appointments_count', 2)
            ->assertJsonPath('data.summary.payment_status_counts.paid', 1)
            ->assertJsonPath('data.summary.payment_status_counts.unpaid', 1)
            ->assertJsonPath('data.summary.payment_methods.0.method', 'upi')
            ->assertJsonPath('data.summary.payment_methods.0.amount', 800);
    }

    public function test_analytics_requires_permission(): void
    {
        $staff = $this->createStaffUser(['phone' => '+917000000052']);
        Sanctum::actingAs($staff);

        $from = now()->subDays(7)->toDateString();
        $to = now()->toDateString();

        $this->getJson("/api/v1/analytics/summary?from={$from}&to={$to}")
            ->assertForbidden();
    }
}
