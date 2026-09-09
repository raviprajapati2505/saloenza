<?php

namespace Tests\Feature\Appointment;

use App\Mail\OutstandingPaymentReminderMail;
use App\Models\Appointment;
use App\Models\Category;
use App\Models\Customer;
use App\Models\SalonServiceProduct;
use App\Models\Saloon;
use App\Models\SaloonBranch;
use App\Models\Service;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AppointmentPaymentApiTest extends TestCase
{
    use RefreshDatabase;

    private Saloon $saloon;

    private SaloonBranch $branch;

    private User $owner;

    private Appointment $appointment;

    protected function setUp(): void
    {
        parent::setUp();

        $this->owner = $this->actingAsOwner(['phone' => '+917000000050']);
        $this->saloon = Saloon::query()->findOrFail($this->owner->saloon_id);

        $this->branch = SaloonBranch::query()->create([
            'saloon_id' => $this->saloon->id,
            'branch_name' => 'Payment Branch',
            'business_address_1' => '123 Test Street',
            'city' => 'Mumbai',
            'state' => 'Maharashtra',
            'area_pincode' => '400001',
            'country' => 'India',
            'is_active' => true,
        ]);

        $customer = Customer::query()->create([
            'name' => 'Pay Customer',
            'phone' => '+919999999999',
            'is_active' => true,
        ]);
        $customer->attachSaloon($this->saloon->id);

        $category = Category::query()->create(['name' => 'Hair', 'is_active' => true]);
        $service = Service::query()->create([
            'name' => 'Haircut',
            'category_id' => $category->id,
            'default_price' => 500,
            'duration_minutes' => 30,
            'is_active' => true,
        ]);

        SalonServiceProduct::query()->create([
            'saloon_id' => $this->saloon->id,
            'branch_id' => null,
            'service_id' => $service->id,
            'product_id' => null,
            'price' => 500,
            'duration_minutes' => 30,
            'is_active' => true,
        ]);

        $starts = now()->addDay()->setTime(10, 0);

        $this->appointment = Appointment::query()->create([
            'saloon_id' => $this->saloon->id,
            'branch_id' => $this->branch->id,
            'customer_id' => $customer->id,
            'service_id' => $service->id,
            'starts_at' => $starts,
            'ends_at' => $starts->copy()->addMinutes(30),
            'status' => 'completed',
            'type' => Appointment::TYPE_APPOINTMENT,
            'price' => 500,
            'discount' => 0,
            'grand_total' => 500,
            'payment_status' => 'unpaid',
            'amount_paid' => 0,
            'created_by' => $this->owner->id,
        ]);
    }

    public function test_owner_can_capture_partial_and_full_payment(): void
    {
        Sanctum::actingAs($this->owner);

        $this->postJson("/api/v1/appointments/{$this->appointment->id}/capture-payment", [
            'amount' => 200,
            'payment_method' => 'cash',
        ])
            ->assertOk()
            ->assertJsonPath('data.appointment.payment_status', 'partial')
            ->assertJsonPath('data.appointment.amount_paid', '200.00')
            ->assertJsonPath('data.appointment.payment_method', 'cash');

        $this->postJson("/api/v1/appointments/{$this->appointment->id}/capture-payment", [
            'amount' => 300,
            'payment_method' => 'upi',
        ])
            ->assertOk()
            ->assertJsonPath('data.appointment.payment_status', 'paid')
            ->assertJsonPath('data.appointment.amount_paid', '500.00');
    }

    public function test_capture_rejects_amount_over_balance(): void
    {
        Sanctum::actingAs($this->owner);

        $this->postJson("/api/v1/appointments/{$this->appointment->id}/capture-payment", [
            'amount' => 600,
            'payment_method' => 'cash',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['amount']);
    }

    public function test_owner_can_refund_collected_payment(): void
    {
        Sanctum::actingAs($this->owner);

        $this->appointment->update([
            'amount_paid' => 500,
            'payment_status' => 'paid',
            'payment_method' => 'card',
            'paid_at' => now(),
        ]);

        $this->postJson("/api/v1/appointments/{$this->appointment->id}/refund-payment")
            ->assertOk()
            ->assertJsonPath('data.appointment.payment_status', 'refunded')
            ->assertJsonPath('data.appointment.amount_paid', '0.00');

        $this->postJson("/api/v1/appointments/{$this->appointment->id}/refund-payment")
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['amount']);
    }

    public function test_booking_can_collect_payment_on_create(): void
    {
        Sanctum::actingAs($this->owner);

        $customer = Customer::query()->firstOrFail();
        $service = Service::query()->firstOrFail();
        $starts = now()->addDays(2)->setTime(11, 0);

        $this->postJson('/api/v1/appointments', [
            'type' => Appointment::TYPE_APPOINTMENT,
            'starts_at' => $starts->toIso8601String(),
            'status' => 'confirmed',
            'customer_id' => $customer->id,
            'branch_id' => $this->branch->id,
            'discount' => 0,
            'collect_payment' => true,
            'payment_method' => 'cash',
            'amount_paid' => 500,
            'services' => [[
                'service_id' => $service->id,
                'price' => 500,
                'duration_minutes' => 30,
            ]],
        ])
            ->assertCreated()
            ->assertJsonPath('data.appointment.payment_status', 'paid')
            ->assertJsonPath('data.appointment.amount_paid', '500.00');
    }

    public function test_future_appointment_defaults_to_scheduled_not_in_progress(): void
    {
        Sanctum::actingAs($this->owner);

        $customer = Customer::query()->firstOrFail();
        $service = Service::query()->firstOrFail();
        $starts = now()->addHours(3);

        $this->postJson('/api/v1/appointments', [
            'type' => Appointment::TYPE_APPOINTMENT,
            'starts_at' => $starts->toIso8601String(),
            'customer_id' => $customer->id,
            'branch_id' => $this->branch->id,
            'services' => [[
                'service_id' => $service->id,
                'price' => 500,
                'duration_minutes' => 30,
            ]],
        ])
            ->assertCreated()
            ->assertJsonPath('data.appointment.status', 'scheduled');
    }

    public function test_cannot_mark_future_appointment_in_progress(): void
    {
        Sanctum::actingAs($this->owner);

        $customer = Customer::query()->firstOrFail();
        $service = Service::query()->firstOrFail();
        $starts = now()->addHours(4);

        $create = $this->postJson('/api/v1/appointments', [
            'type' => Appointment::TYPE_APPOINTMENT,
            'starts_at' => $starts->toIso8601String(),
            'status' => 'confirmed',
            'customer_id' => $customer->id,
            'branch_id' => $this->branch->id,
            'services' => [[
                'service_id' => $service->id,
                'price' => 500,
                'duration_minutes' => 30,
            ]],
        ])->assertCreated();

        $appointmentId = $create->json('data.appointment.id');

        $this->putJson("/api/v1/appointments/{$appointmentId}", [
            'type' => Appointment::TYPE_APPOINTMENT,
            'starts_at' => $starts->toIso8601String(),
            'status' => 'in-progress',
            'customer_id' => $customer->id,
            'branch_id' => $this->branch->id,
            'services' => [[
                'service_id' => $service->id,
                'price' => 500,
                'duration_minutes' => 30,
            ]],
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['status']);
    }

    public function test_walk_in_defaults_to_completed_with_pending_payment(): void
    {
        Sanctum::actingAs($this->owner);

        $customer = Customer::query()->firstOrFail();
        $service = Service::query()->firstOrFail();

        $this->postJson('/api/v1/appointments', [
            'type' => Appointment::TYPE_WALK_IN,
            'starts_at' => now()->toIso8601String(),
            'customer_id' => $customer->id,
            'branch_id' => $this->branch->id,
            'services' => [[
                'service_id' => $service->id,
                'price' => 500,
                'duration_minutes' => 30,
            ]],
        ])
            ->assertCreated()
            ->assertJsonPath('data.appointment.status', 'completed')
            ->assertJsonPath('data.appointment.payment_status', 'unpaid')
            ->assertJsonPath('data.appointment.balance_due', 500);
    }

    public function test_owner_can_send_a_payment_reminder(): void
    {
        Sanctum::actingAs($this->owner);
        Mail::fake();

        $this->appointment->customer?->update(['email' => 'pay.customer@test.com']);

        $this->postJson("/api/v1/appointments/{$this->appointment->id}/remind-payment")
            ->assertOk()
            ->assertJsonPath('message', 'Payment reminder sent.');

        $this->assertNotNull($this->appointment->fresh()->payment_reminder_sent_at);
        Mail::assertSent(OutstandingPaymentReminderMail::class);
    }
}
