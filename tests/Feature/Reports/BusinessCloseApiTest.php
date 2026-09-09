<?php

namespace Tests\Feature\Reports;

use App\Models\Appointment;
use App\Models\AppointmentService;
use App\Models\Category;
use App\Models\Customer;
use App\Models\Expense;
use App\Models\SaloonBranch;
use App\Models\Service;
use App\Support\Expense\ExpenseCategory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class BusinessCloseApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_fetch_business_close_with_staff_work_and_cash(): void
    {
        $owner = $this->actingAsOwner(['phone' => '+917000000401']);
        $this->assignDefaultSubscription($owner->saloon, 'pro');

        $branch = SaloonBranch::query()->create([
            'saloon_id' => $owner->saloon_id,
            'branch_name' => 'Close Branch',
            'business_address_1' => '1 Test Street',
            'city' => 'Mumbai',
            'state' => 'MH',
            'area_pincode' => '400001',
            'country' => 'India',
            'is_active' => true,
        ]);

        $staff = $this->createStaffUser([
            'saloon' => $owner->saloon,
            'branch_id' => $branch->id,
            'commission_rate' => 20,
            'phone' => '+917000000402',
        ]);
        $this->assignDefaultSubscription($owner->saloon, 'pro');
        Sanctum::actingAs($owner);

        $customer = Customer::query()->create([
            'name' => 'Close Customer',
            'phone' => '+919666666666',
            'is_active' => true,
        ]);
        $customer->attachSaloon((int) $owner->saloon_id);

        $category = Category::query()->create(['name' => 'Hair', 'is_active' => true]);
        $service = Service::query()->create([
            'name' => 'Cut',
            'category_id' => $category->id,
            'default_price' => 1000,
            'duration_minutes' => 60,
            'is_active' => true,
        ]);

        $day = now()->setTime(11, 0);
        $appointment = Appointment::query()->create([
            'saloon_id' => $owner->saloon_id,
            'branch_id' => $branch->id,
            'customer_id' => $customer->id,
            'staff_id' => $staff->id,
            'service_id' => $service->id,
            'starts_at' => $day,
            'ends_at' => $day->copy()->addHour(),
            'status' => 'completed',
            'type' => Appointment::TYPE_APPOINTMENT,
            'price' => 1000,
            'services_total' => 1000,
            'products_total' => 0,
            'grand_total' => 1000,
            'payment_status' => 'paid',
            'payment_method' => 'cash',
            'amount_paid' => 1000,
            'created_by' => $owner->id,
        ]);

        AppointmentService::query()->create([
            'appointment_id' => $appointment->id,
            'service_id' => $service->id,
            'staff_id' => $staff->id,
            'price' => 1000,
            'duration_minutes' => 60,
            'starts_at' => $appointment->starts_at,
            'ends_at' => $appointment->ends_at,
            'sort_order' => 0,
        ]);

        Expense::query()->create([
            'saloon_id' => $owner->saloon_id,
            'branch_id' => $branch->id,
            'category' => ExpenseCategory::UTILITIES,
            'title' => 'Power',
            'amount' => 200,
            'incurred_on' => $day->toDateString(),
            'created_by' => $owner->id,
        ]);

        Sanctum::actingAs($owner);

        $from = $day->toDateString();

        $this->getJson("/api/v1/reports/business-close?from={$from}&to={$from}&branch_id={$branch->id}")
            ->assertOk()
            ->assertJsonPath('data.summary.summary.collected', 1000)
            ->assertJsonPath('data.summary.summary.expenses', 200)
            ->assertJsonPath('data.summary.summary.net_collected', 800)
            ->assertJsonPath('data.summary.summary.staff_commission', 200)
            ->assertJsonPath('data.summary.staff.0.staff_id', $staff->id)
            ->assertJsonPath('data.summary.staff.0.hours_worked', 1)
            ->assertJsonPath('data.summary.staff.0.commission', 200)
            ->assertJsonPath('data.summary.payment_methods.0.method', 'cash');
    }
}
