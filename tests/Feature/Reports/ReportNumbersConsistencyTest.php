<?php

namespace Tests\Feature\Reports;

use App\Models\Appointment;
use App\Models\AppointmentProduct;
use App\Models\AppointmentService;
use App\Models\Category;
use App\Models\Customer;
use App\Models\Product;
use App\Models\SaloonBranch;
use App\Models\Service;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ReportNumbersConsistencyTest extends TestCase
{
    use RefreshDatabase;

    public function test_day_close_staff_earnings_and_analytics_agree_on_work_and_cash(): void
    {
        $owner = $this->actingAsOwner(['phone' => '+917000000501']);
        $this->assignDefaultSubscription($owner->saloon, 'pro');

        $branch = SaloonBranch::query()->create([
            'saloon_id' => $owner->saloon_id,
            'branch_name' => 'Consistency Branch',
            'business_address_1' => '1 Test Street',
            'city' => 'Mumbai',
            'state' => 'MH',
            'area_pincode' => '400001',
            'country' => 'India',
            'is_active' => true,
        ]);

        $stylist = $this->createStaffUser([
            'saloon' => $owner->saloon,
            'branch_id' => $branch->id,
            'commission_rate' => 20,
            'phone' => '+917000000502',
        ]);
        $seller = $this->createStaffUser([
            'saloon' => $owner->saloon,
            'branch_id' => $branch->id,
            'commission_rate' => 10,
            'phone' => '+917000000503',
        ]);
        $this->assignDefaultSubscription($owner->saloon, 'pro');

        $customer = Customer::query()->create([
            'name' => 'Consistency Customer',
            'phone' => '+919555555555',
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
        $product = Product::query()->create([
            'name' => 'Serum',
            'category_id' => $category->id,
            'is_active' => true,
        ]);

        $day = now()->setTime(11, 0);

        // Completed service visit — counts in Day close staff + Staff earnings.
        $completed = Appointment::query()->create([
            'saloon_id' => $owner->saloon_id,
            'branch_id' => $branch->id,
            'customer_id' => $customer->id,
            'staff_id' => $stylist->id,
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
            'appointment_id' => $completed->id,
            'service_id' => $service->id,
            'staff_id' => $stylist->id,
            'price' => 1000,
            'duration_minutes' => 60,
            'starts_at' => $completed->starts_at,
            'ends_at' => $completed->ends_at,
            'sort_order' => 0,
        ]);

        // Scheduled — must not attribute staff revenue (matches earnings statuses).
        $scheduled = Appointment::query()->create([
            'saloon_id' => $owner->saloon_id,
            'branch_id' => $branch->id,
            'customer_id' => $customer->id,
            'staff_id' => $stylist->id,
            'service_id' => $service->id,
            'starts_at' => $day->copy()->addHours(2),
            'ends_at' => $day->copy()->addHours(3),
            'status' => 'scheduled',
            'type' => Appointment::TYPE_APPOINTMENT,
            'price' => 1000,
            'services_total' => 1000,
            'products_total' => 0,
            'grand_total' => 1000,
            'payment_status' => 'unpaid',
            'amount_paid' => 0,
            'created_by' => $owner->id,
        ]);
        AppointmentService::query()->create([
            'appointment_id' => $scheduled->id,
            'service_id' => $service->id,
            'staff_id' => $stylist->id,
            'price' => 1000,
            'duration_minutes' => 60,
            'starts_at' => $scheduled->starts_at,
            'ends_at' => $scheduled->ends_at,
            'sort_order' => 0,
        ]);

        // Cancelled with a leftover payment — excluded from collected / payment methods.
        Appointment::query()->create([
            'saloon_id' => $owner->saloon_id,
            'branch_id' => $branch->id,
            'customer_id' => $customer->id,
            'staff_id' => $stylist->id,
            'service_id' => $service->id,
            'starts_at' => $day->copy()->addHours(4),
            'ends_at' => $day->copy()->addHours(5),
            'status' => 'cancelled',
            'type' => Appointment::TYPE_APPOINTMENT,
            'price' => 500,
            'services_total' => 500,
            'products_total' => 0,
            'grand_total' => 500,
            'payment_status' => 'paid',
            'payment_method' => 'upi',
            'amount_paid' => 500,
            'created_by' => $owner->id,
        ]);

        // Product-only sale attributed to seller (no header staff / service lines).
        $productSale = Appointment::query()->create([
            'saloon_id' => $owner->saloon_id,
            'branch_id' => $branch->id,
            'customer_id' => $customer->id,
            'staff_id' => null,
            'service_id' => null,
            'starts_at' => $day->copy()->addMinutes(30),
            'ends_at' => $day->copy()->addMinutes(40),
            'status' => 'completed',
            'type' => Appointment::TYPE_WALK_IN,
            'price' => 400,
            'services_total' => 0,
            'products_total' => 400,
            'grand_total' => 400,
            'payment_status' => 'paid',
            'payment_method' => 'card',
            'amount_paid' => 400,
            'created_by' => $owner->id,
        ]);
        AppointmentProduct::query()->create([
            'appointment_id' => $productSale->id,
            'product_id' => $product->id,
            'branch_id' => $branch->id,
            'staff_id' => $seller->id,
            'quantity' => 1,
            'unit_price' => 400,
            'unit_cost' => 100,
            'line_total' => 400,
            'sort_order' => 0,
        ]);

        Sanctum::actingAs($owner);
        $from = $day->toDateString();

        $close = $this->getJson("/api/v1/reports/business-close?from={$from}&to={$from}&branch_id={$branch->id}")
            ->assertOk()
            ->json('data.summary');

        $analytics = $this->getJson("/api/v1/analytics/summary?from={$from}&to={$from}&branch_id={$branch->id}")
            ->assertOk()
            ->json('data.summary');

        $stylistEarnings = $this->getJson("/api/v1/staff/earnings?staff_id={$stylist->id}&from={$from}&to={$from}")
            ->assertOk()
            ->json('data.report');

        $sellerEarnings = $this->getJson("/api/v1/staff/earnings?staff_id={$seller->id}&from={$from}&to={$from}")
            ->assertOk()
            ->json('data.report');

        // Cash: cancelled payment excluded; completed cash + card only.
        $this->assertSame(1400.0, (float) $close['summary']['collected']);
        $this->assertSame(1400.0, (float) $analytics['collected_revenue']);

        $closeMethods = collect($close['payment_methods'])->pluck('amount', 'method')->all();
        $this->assertSame(1000.0, (float) ($closeMethods['cash'] ?? 0));
        $this->assertSame(400.0, (float) ($closeMethods['card'] ?? 0));
        $this->assertArrayNotHasKey('upi', $closeMethods);

        $analyticsMethods = collect($analytics['payment_methods'])->pluck('amount', 'method')->all();
        $this->assertSame(1000.0, (float) ($analyticsMethods['cash'] ?? 0));
        $this->assertSame(400.0, (float) ($analyticsMethods['card'] ?? 0));
        $this->assertArrayNotHasKey('upi', $analyticsMethods);

        $closeByStaff = collect($close['staff'])->keyBy('staff_id');

        // Stylist: only completed Cut (scheduled ignored).
        $this->assertSame(1000.0, (float) $closeByStaff[$stylist->id]['revenue_generated']);
        $this->assertSame(1000.0, (float) $stylistEarnings['summary']['revenue_generated']);
        $this->assertSame(1, (int) $stylistEarnings['summary']['services_performed']);
        $this->assertSame(
            (float) $closeByStaff[$stylist->id]['collected'],
            (float) $stylistEarnings['summary']['collected'],
        );

        // Seller: product-only appointment included in both reports.
        $this->assertSame(400.0, (float) $closeByStaff[$seller->id]['revenue_generated']);
        $this->assertSame(400.0, (float) $sellerEarnings['summary']['revenue_generated']);
        $this->assertSame(1, (int) $sellerEarnings['summary']['services_performed']);
        $this->assertSame(
            (float) $closeByStaff[$seller->id]['collected'],
            (float) $sellerEarnings['summary']['collected'],
        );
    }
}
