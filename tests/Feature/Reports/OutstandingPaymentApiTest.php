<?php

namespace Tests\Feature\Reports;

use App\Mail\OutstandingPaymentReminderMail;
use App\Models\Appointment;
use App\Models\Customer;
use App\Models\SalonSetting;
use App\Models\Saloon;
use App\Models\SaloonBranch;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class OutstandingPaymentApiTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private Saloon $saloon;

    private SaloonBranch $branch;

    public function test_outstanding_report_splits_paid_and_pending_by_customer(): void
    {
        Sanctum::actingAs($this->owner);

        $paidCustomer = $this->makeCustomer('Paid Customer', '+919111111111');
        $dueCustomer = $this->makeCustomer('Due Customer', '+919222222222');
        $today = now()->setTime(11, 0);

        Appointment::query()->create([
            'saloon_id' => $this->saloon->id,
            'branch_id' => $this->branch->id,
            'customer_id' => $paidCustomer->id,
            'starts_at' => $today,
            'ends_at' => $today->copy()->addMinutes(30),
            'status' => 'completed',
            'type' => Appointment::TYPE_WALK_IN,
            'price' => 700,
            'grand_total' => 700,
            'payment_status' => 'paid',
            'amount_paid' => 700,
            'created_by' => $this->owner->id,
        ]);

        Appointment::query()->create([
            'saloon_id' => $this->saloon->id,
            'branch_id' => $this->branch->id,
            'customer_id' => $dueCustomer->id,
            'starts_at' => $today,
            'ends_at' => $today->copy()->addMinutes(45),
            'status' => 'completed',
            'type' => Appointment::TYPE_WALK_IN,
            'price' => 900,
            'grand_total' => 900,
            'payment_status' => 'unpaid',
            'amount_paid' => 0,
            'created_by' => $this->owner->id,
        ]);

        $from = $today->toDateString();

        $this->getJson("/api/v1/reports/outstanding-payments?from={$from}&to={$from}")
            ->assertOk()
            ->assertJsonPath('data.summary.paid_bills', 1)
            ->assertJsonPath('data.summary.pending_bills', 1)
            ->assertJsonPath('data.summary.outstanding', 900)
            ->assertJsonPath('data.summary.customers.0.name', 'Due Customer')
            ->assertJsonPath('data.summary.customers.0.outstanding', 900);
    }

    public function test_outstanding_report_includes_older_open_balances(): void
    {
        Sanctum::actingAs($this->owner);

        $customer = $this->makeCustomer('Older Due Customer', '+919444444444');
        $oldVisit = now()->subDays(12)->setTime(14, 0);
        $today = now()->toDateString();

        Appointment::query()->create([
            'saloon_id' => $this->saloon->id,
            'branch_id' => $this->branch->id,
            'customer_id' => $customer->id,
            'starts_at' => $oldVisit,
            'ends_at' => $oldVisit->copy()->addMinutes(30),
            'status' => 'completed',
            'type' => Appointment::TYPE_WALK_IN,
            'price' => 650,
            'grand_total' => 650,
            'payment_status' => 'unpaid',
            'amount_paid' => 0,
            'created_by' => $this->owner->id,
        ]);

        $this->getJson("/api/v1/reports/outstanding-payments?from={$today}&to={$today}")
            ->assertOk()
            ->assertJsonPath('data.summary.paid_bills', 0)
            ->assertJsonPath('data.summary.pending_bills', 1)
            ->assertJsonPath('data.summary.outstanding', 650)
            ->assertJsonPath('data.summary.customers.0.name', 'Older Due Customer');
    }

    public function test_automatic_reminders_respect_salon_overdue_setting(): void
    {
        Mail::fake();

        $customer = $this->makeCustomer('Overdue Customer', '+919333333333');
        $visit = now()->subDays(4)->setTime(11, 0);

        Appointment::query()->create([
            'saloon_id' => $this->saloon->id,
            'branch_id' => $this->branch->id,
            'customer_id' => $customer->id,
            'starts_at' => $visit,
            'ends_at' => $visit->copy()->addMinutes(30),
            'status' => 'completed',
            'type' => Appointment::TYPE_WALK_IN,
            'price' => 400,
            'grand_total' => 400,
            'payment_status' => 'unpaid',
            'amount_paid' => 0,
            'created_by' => $this->owner->id,
        ]);

        Artisan::call('payments:send-outstanding-reminders');
        Mail::assertNothingSent();

        SalonSetting::query()->create([
            'saloon_id' => $this->saloon->id,
            'group' => 'notifications',
            'key' => 'payment_outstanding_reminder',
            'value' => true,
        ]);
        SalonSetting::query()->create([
            'saloon_id' => $this->saloon->id,
            'group' => 'notifications',
            'key' => 'outstanding_overdue_days',
            'value' => 3,
        ]);
        Cache::forget('salon_settings.'.$this->saloon->id);

        Artisan::call('payments:send-outstanding-reminders');
        Mail::assertSent(OutstandingPaymentReminderMail::class);
    }

    private function makeCustomer(string $name, string $phone): Customer
    {
        $customer = Customer::query()->create([
            'name' => $name,
            'phone' => $phone,
            'email' => strtolower(str_replace(' ', '.', $name)).'@test.com',
            'is_active' => true,
        ]);
        $customer->attachSaloon($this->saloon->id);

        return $customer;
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->owner = $this->actingAsOwner(['phone' => '+917000000095']);
        $this->saloon = Saloon::query()->findOrFail($this->owner->saloon_id);
        $this->assignDefaultSubscription($this->saloon, 'pro');

        $this->branch = SaloonBranch::query()->create([
            'saloon_id' => $this->saloon->id,
            'branch_name' => 'Ledger Branch',
            'business_address_1' => '11 Due Street',
            'city' => 'Mumbai',
            'state' => 'Maharashtra',
            'area_pincode' => '400001',
            'country' => 'India',
            'is_active' => true,
        ]);
    }
}
