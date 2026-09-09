<?php

namespace Tests\Feature\Appointment;

use App\Models\Appointment;
use App\Models\AppointmentService;
use App\Models\Category;
use App\Models\Customer;
use App\Models\SalonServiceProduct;
use App\Models\Service;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AppointmentStaffAssignmentTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array{cut: Service, color: Service}
     */
    private function seedCatalog(User $staff): array
    {
        $category = Category::query()->create(['name' => 'Hair', 'is_active' => true]);
        $cut = Service::query()->create([
            'name' => 'Cut',
            'category_id' => $category->id,
            'default_price' => 500,
            'duration_minutes' => 30,
            'is_active' => true,
        ]);
        $color = Service::query()->create([
            'name' => 'Color',
            'category_id' => $category->id,
            'default_price' => 1200,
            'duration_minutes' => 60,
            'is_active' => true,
        ]);

        foreach ([$cut, $color] as $service) {
            SalonServiceProduct::query()->create([
                'saloon_id' => $staff->saloon_id,
                'branch_id' => $staff->branch_id,
                'service_id' => $service->id,
                'product_id' => null,
                'price' => (float) $service->default_price,
                'duration_minutes' => (int) $service->duration_minutes,
                'is_active' => true,
            ]);
        }

        return ['cut' => $cut, 'color' => $color];
    }

    public function test_rejects_same_staff_with_overlapping_lines_in_one_request(): void
    {
        $owner = $this->actingAsOwner(['phone' => '+919111111110']);
        Sanctum::actingAs($owner);

        $customer = Customer::query()->create([
            'name' => 'Overlap Customer',
            'phone' => '+919111111111',
            'is_active' => true,
        ]);
        $customer->attachSaloon($owner->saloon_id);

        ['cut' => $cut, 'color' => $color] = $this->seedCatalog($owner);

        $start = now()->addHours(2)->startOfMinute();

        $this->postJson('/api/v1/appointments', [
            'branch_id' => $owner->branch_id,
            'customer_id' => $customer->id,
            'starts_at' => $start->toIso8601String(),
            'status' => 'confirmed',
            'type' => 'appointment',
            'services' => [
                [
                    'service_id' => $cut->id,
                    'staff_id' => $owner->id,
                    'price' => 500,
                    'duration_minutes' => 30,
                    'starts_at' => $start->toIso8601String(),
                    'ends_at' => $start->copy()->addMinutes(30)->toIso8601String(),
                ],
                [
                    'service_id' => $color->id,
                    'staff_id' => $owner->id,
                    'price' => 1200,
                    'duration_minutes' => 60,
                    'starts_at' => $start->copy()->addMinutes(15)->toIso8601String(),
                    'ends_at' => $start->copy()->addMinutes(75)->toIso8601String(),
                ],
            ],
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['services.1.staff_id']);
    }

    public function test_status_update_preserves_per_line_staff_and_schedule(): void
    {
        $owner = $this->actingAsOwner(['phone' => '+919333333330']);
        $otherStaff = User::factory()->create([
            'saloon_id' => $owner->saloon_id,
            'branch_id' => $owner->branch_id,
            'role_id' => $owner->role_id,
            'is_active' => true,
            'phone' => '+919222222222',
        ]);

        Sanctum::actingAs($owner);

        $customer = Customer::query()->create([
            'name' => 'Combo Customer',
            'phone' => '+919333333331',
            'is_active' => true,
        ]);
        $customer->attachSaloon($owner->saloon_id);

        ['cut' => $cut, 'color' => $color] = $this->seedCatalog($owner);

        $start = now()->subMinutes(10)->startOfMinute();
        $lineTwoStart = $start->copy()->addMinutes(30);
        $lineTwoEnd = $lineTwoStart->copy()->addMinutes(60);

        $appointment = Appointment::query()->create([
            'saloon_id' => $owner->saloon_id,
            'branch_id' => $owner->branch_id,
            'customer_id' => $customer->id,
            'staff_id' => $owner->id,
            'service_id' => $cut->id,
            'starts_at' => $start,
            'ends_at' => $lineTwoEnd,
            'status' => 'confirmed',
            'type' => 'appointment',
            'price' => 1700,
            'grand_total' => 1700,
        ]);

        AppointmentService::query()->create([
            'appointment_id' => $appointment->id,
            'service_id' => $cut->id,
            'staff_id' => $owner->id,
            'price' => 500,
            'duration_minutes' => 30,
            'starts_at' => $start,
            'ends_at' => $start->copy()->addMinutes(30),
            'sort_order' => 0,
        ]);

        AppointmentService::query()->create([
            'appointment_id' => $appointment->id,
            'service_id' => $color->id,
            'staff_id' => $otherStaff->id,
            'price' => 1200,
            'duration_minutes' => 60,
            'starts_at' => $lineTwoStart,
            'ends_at' => $lineTwoEnd,
            'sort_order' => 1,
        ]);

        $this->putJson("/api/v1/appointments/{$appointment->id}", [
            'branch_id' => $owner->branch_id,
            'customer_id' => $customer->id,
            'starts_at' => $start->toIso8601String(),
            'status' => 'in-progress',
            'type' => 'appointment',
            'services' => [
                [
                    'service_id' => $cut->id,
                    'staff_id' => $owner->id,
                    'price' => 500,
                    'duration_minutes' => 30,
                ],
                [
                    'service_id' => $color->id,
                    'staff_id' => $otherStaff->id,
                    'price' => 1200,
                    'duration_minutes' => 60,
                ],
            ],
        ])->assertOk();

        $appointment->refresh()->load('services');

        $this->assertSame('in-progress', $appointment->status);
        $this->assertSame($lineTwoEnd->toDateTimeString(), $appointment->ends_at->toDateTimeString());

        $lines = $appointment->services->sortBy('sort_order')->values();
        $this->assertSame($owner->id, $lines[0]->staff_id);
        $this->assertSame($otherStaff->id, $lines[1]->staff_id);
        $this->assertSame($lineTwoStart->toDateTimeString(), $lines[1]->starts_at->toDateTimeString());
        $this->assertSame($lineTwoEnd->toDateTimeString(), $lines[1]->ends_at->toDateTimeString());
    }

    public function test_can_complete_appointment_without_rechecking_staff_conflicts(): void
    {
        $owner = $this->actingAsOwner(['phone' => '+919333333332']);
        $otherStaff = User::factory()->create([
            'saloon_id' => $owner->saloon_id,
            'branch_id' => $owner->branch_id,
            'role_id' => $owner->role_id,
            'is_active' => true,
            'phone' => '+919222222223',
        ]);

        Sanctum::actingAs($owner);

        $customerA = Customer::query()->create([
            'name' => 'Aditi Verma',
            'phone' => '+919333333332',
            'is_active' => true,
        ]);
        $customerB = Customer::query()->create([
            'name' => 'Vikram Singh',
            'phone' => '+919333333333',
            'is_active' => true,
        ]);
        $customerA->attachSaloon($owner->saloon_id);
        $customerB->attachSaloon($owner->saloon_id);

        ['cut' => $cut, 'color' => $color] = $this->seedCatalog($owner);

        $start = now()->subMinutes(20)->startOfMinute();

        $aditi = Appointment::query()->create([
            'saloon_id' => $owner->saloon_id,
            'branch_id' => $owner->branch_id,
            'customer_id' => $customerA->id,
            'staff_id' => $owner->id,
            'service_id' => $cut->id,
            'starts_at' => $start,
            'ends_at' => $start->copy()->addMinutes(30),
            'status' => 'in-progress',
            'type' => 'appointment',
            'price' => 500,
            'grand_total' => 500,
        ]);

        AppointmentService::query()->create([
            'appointment_id' => $aditi->id,
            'service_id' => $cut->id,
            'staff_id' => $owner->id,
            'price' => 500,
            'duration_minutes' => 30,
            'starts_at' => $start,
            'ends_at' => $start->copy()->addMinutes(30),
            'sort_order' => 0,
        ]);

        $vikramStart = now()->subMinutes(10)->startOfMinute();

        $vikram = Appointment::query()->create([
            'saloon_id' => $owner->saloon_id,
            'branch_id' => $owner->branch_id,
            'customer_id' => $customerB->id,
            'staff_id' => $owner->id,
            'service_id' => $color->id,
            'starts_at' => $vikramStart,
            'ends_at' => $vikramStart->copy()->addMinutes(60),
            'status' => 'confirmed',
            'type' => 'walk_in',
            'price' => 1200,
            'grand_total' => 1200,
        ]);

        AppointmentService::query()->create([
            'appointment_id' => $vikram->id,
            'service_id' => $color->id,
            'staff_id' => $owner->id,
            'price' => 1200,
            'duration_minutes' => 60,
            'starts_at' => $vikramStart,
            'ends_at' => $vikramStart->copy()->addMinutes(60),
            'sort_order' => 0,
        ]);

        $this->putJson("/api/v1/appointments/{$aditi->id}", [
            'branch_id' => $owner->branch_id,
            'customer_id' => $customerA->id,
            'starts_at' => $start->toIso8601String(),
            'status' => 'completed',
            'type' => 'appointment',
            'services' => [
                [
                    'service_id' => $cut->id,
                    'staff_id' => $owner->id,
                    'price' => 500,
                    'duration_minutes' => 30,
                ],
            ],
        ])->assertOk();

        $this->assertSame('completed', $aditi->fresh()->status);
    }

    public function test_rejects_staff_outside_weekly_schedule_on_internal_booking(): void
    {
        $owner = $this->actingAsOwner(['phone' => '+919111111113']);
        $owner->update([
            'weekly_schedule' => [
                'monday' => ['enabled' => true, 'open' => '09:00', 'close' => '17:00'],
                'tuesday' => ['enabled' => true, 'open' => '09:00', 'close' => '17:00'],
                'wednesday' => ['enabled' => true, 'open' => '09:00', 'close' => '17:00'],
                'thursday' => ['enabled' => true, 'open' => '09:00', 'close' => '17:00'],
                'friday' => ['enabled' => true, 'open' => '09:00', 'close' => '17:00'],
                'saturday' => ['enabled' => false, 'open' => '09:00', 'close' => '17:00'],
                'sunday' => ['enabled' => false, 'open' => '09:00', 'close' => '17:00'],
            ],
        ]);
        Sanctum::actingAs($owner);

        $customer = Customer::query()->create([
            'name' => 'Schedule Customer',
            'phone' => '+919111111114',
            'is_active' => true,
        ]);
        $customer->attachSaloon($owner->saloon_id);

        ['cut' => $cut] = $this->seedCatalog($owner);

        $start = now()->next('Saturday')->setTime(11, 0)->startOfMinute();

        $this->postJson('/api/v1/appointments', [
            'branch_id' => $owner->branch_id,
            'customer_id' => $customer->id,
            'starts_at' => $start->toIso8601String(),
            'status' => 'confirmed',
            'type' => 'appointment',
            'services' => [
                [
                    'service_id' => $cut->id,
                    'staff_id' => $owner->id,
                    'price' => 500,
                    'duration_minutes' => 30,
                ],
            ],
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['services.0.staff_id']);
    }
}
