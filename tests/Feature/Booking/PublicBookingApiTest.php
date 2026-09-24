<?php

namespace Tests\Feature\Booking;

use App\Models\Category;
use App\Models\SalonBookingLink;
use App\Models\SalonServiceProduct;
use App\Models\Service;
use App\Models\User;
use App\Support\Role\RoleCodes;
use Database\Seeders\ApplicationPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PublicBookingApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_booking_creates_confirmed_appointment(): void
    {
        $owner = $this->actingAsOwner();
        $branch = $owner->branch_id
            ? \App\Models\SaloonBranch::query()->find($owner->branch_id)
            : \App\Models\SaloonBranch::query()->where('saloon_id', $owner->saloon_id)->first();

        if ($branch === null) {
            $branch = \App\Models\SaloonBranch::query()->create([
                'saloon_id' => $owner->saloon_id,
                'branch_name' => 'Booking Branch',
                'business_address_1' => '123 Test Street',
                'city' => 'Mumbai',
                'state' => 'Maharashtra',
                'area_pincode' => '400001',
                'country' => 'India',
                'is_active' => true,
            ]);
        }

        $staff = $this->createStaffUser([
            'saloon_id' => $owner->saloon_id,
            'branch_id' => $branch->id,
        ]);

        $category = Category::query()->create(['name' => 'Spa', 'is_active' => true]);
        $service = Service::query()->create([
            'name' => 'Facial',
            'category_id' => $category->id,
            'default_price' => 1200,
            'duration_minutes' => 45,
            'is_active' => true,
        ]);

        SalonServiceProduct::query()->create([
            'saloon_id' => $owner->saloon_id,
            'branch_id' => $branch->id,
            'service_id' => $service->id,
            'product_id' => null,
            'price' => 1200,
            'duration_minutes' => 45,
            'is_active' => true,
        ]);

        $link = SalonBookingLink::query()->create([
            'saloon_id' => $owner->saloon_id,
            'branch_id' => $branch->id,
            'token' => 'demo-booking-token',
            'label' => 'Demo link',
            'is_active' => true,
            'created_by' => $owner->id,
        ]);

        $startsAt = now()->addDay()->setTime(11, 0);

        $this->postJson('/api/v1/public/book/'.$link->token, [
            'service_ids' => [$service->id],
            'staff_id' => $staff->id,
            'starts_at' => $startsAt->toISOString(),
            'customer_name' => 'Online Guest',
            'customer_phone' => '+919444444444',
        ])
            ->assertCreated()
            ->assertJsonPath('data.appointment.booking_source', 'self_booking')
            ->assertJsonPath('data.appointment.status', 'confirmed');

        $this->assertDatabaseHas('appointments', [
            'saloon_id' => $owner->saloon_id,
            'booking_source' => 'self_booking',
        ]);

        $this->assertDatabaseHas('customers', [
            'phone' => '+919444444444',
        ]);
    }

    public function test_public_booking_accepts_variant_only_branch_catalog(): void
    {
        $owner = $this->actingAsOwner();
        $branch = \App\Models\SaloonBranch::query()->create([
            'saloon_id' => $owner->saloon_id,
            'branch_name' => 'Public Variant Branch',
            'business_address_1' => '123 Test Street',
            'city' => 'Mumbai',
            'state' => 'Maharashtra',
            'area_pincode' => '400001',
            'country' => 'India',
            'is_active' => true,
        ]);

        $staff = $this->createStaffUser([
            'saloon_id' => $owner->saloon_id,
            'branch_id' => $branch->id,
        ]);

        $category = Category::query()->create(['name' => 'Spa', 'is_active' => true]);
        $service = Service::query()->create([
            'name' => 'Variant Facial',
            'category_id' => $category->id,
            'default_price' => 1200,
            'duration_minutes' => 45,
            'is_active' => true,
        ]);
        $product = \App\Models\Product::query()->create([
            'name' => 'Spa Kit',
            'category_id' => $category->id,
            'default_price' => 100,
            'is_active' => true,
        ]);

        SalonServiceProduct::query()->create([
            'saloon_id' => $owner->saloon_id,
            'branch_id' => $branch->id,
            'service_id' => $service->id,
            'product_id' => $product->id,
            'price' => 1200,
            'duration_minutes' => 45,
            'is_active' => true,
        ]);

        $link = SalonBookingLink::query()->create([
            'saloon_id' => $owner->saloon_id,
            'branch_id' => $branch->id,
            'token' => 'variant-booking-token',
            'label' => 'Variant link',
            'is_active' => true,
            'created_by' => $owner->id,
        ]);

        $this->getJson('/api/v1/public/book/'.$link->token)
            ->assertOk()
            ->assertJsonPath('data.services.0.id', $service->id)
            ->assertJsonPath('data.staff.0.id', $staff->id);

        $startsAt = now()->addDay()->setTime(12, 0);

        $this->postJson('/api/v1/public/book/'.$link->token, [
            'service_ids' => [$service->id],
            'staff_id' => $staff->id,
            'starts_at' => $startsAt->toISOString(),
            'customer_name' => 'Variant Guest',
            'customer_phone' => '+919333222111',
        ])->assertCreated();
    }

    public function test_staff_can_create_booking_link(): void
    {
        $owner = $this->actingAsOwner();
        $this->seed(ApplicationPermissionSeeder::class);

        $role = \App\Models\Role::findByCode(RoleCodes::SALON_FRANCHISE_OWNER);
        $role->permissions()->syncWithoutDetaching(
            \App\Models\Permission::query()->whereIn('code', [
                'booking_links.view',
                'booking_links.manage',
            ])->pluck('id'),
        );

        Sanctum::actingAs($owner);

        $this->postJson('/api/v1/booking-links', [
            'label' => 'Front desk link',
        ])
            ->assertCreated()
            ->assertJsonStructure([
                'data' => [
                    'link' => ['token', 'url', 'path'],
                ],
            ]);
    }

    public function test_public_booking_slots_respect_staff_weekly_schedule(): void
    {
        $owner = $this->actingAsOwner();
        $branch = \App\Models\SaloonBranch::query()->create([
            'saloon_id' => $owner->saloon_id,
            'branch_name' => 'Schedule Test Branch',
            'business_address_1' => '123 Test Street',
            'city' => 'Mumbai',
            'state' => 'Maharashtra',
            'area_pincode' => '400001',
            'country' => 'India',
            'is_active' => true,
        ]);

        $staff = $this->createStaffUser([
            'saloon_id' => $owner->saloon_id,
            'branch_id' => $branch->id,
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

        $category = Category::query()->create(['name' => 'Hair', 'is_active' => true]);
        $service = Service::query()->create([
            'name' => 'Trim',
            'category_id' => $category->id,
            'default_price' => 500,
            'duration_minutes' => 30,
            'is_active' => true,
        ]);

        SalonServiceProduct::query()->create([
            'saloon_id' => $owner->saloon_id,
            'branch_id' => $branch->id,
            'service_id' => $service->id,
            'product_id' => null,
            'price' => 500,
            'duration_minutes' => 30,
            'is_active' => true,
        ]);

        $link = SalonBookingLink::query()->create([
            'saloon_id' => $owner->saloon_id,
            'branch_id' => $branch->id,
            'token' => 'schedule-booking-token',
            'label' => 'Schedule test',
            'is_active' => true,
            'created_by' => $owner->id,
        ]);

        $saturday = now()->next('Saturday')->toDateString();

        $saturdaySlots = $this->getJson(
            '/api/v1/public/book/'.$link->token.'/slots?date='.$saturday.'&staff_id='.$staff->id.'&service_ids[]='.$service->id
        )
            ->assertOk()
            ->json('data.slots');

        $this->assertTrue(
            collect($saturdaySlots)->every(fn (array $slot): bool => $slot['available'] === false)
        );

        $monday = now()->next('Monday')->toDateString();

        $mondaySlots = $this->getJson(
            '/api/v1/public/book/'.$link->token.'/slots?date='.$monday.'&staff_id='.$staff->id.'&service_ids[]='.$service->id
        )
            ->assertOk()
            ->json('data.slots');

        $this->assertTrue(
            collect($mondaySlots)->contains(fn (array $slot): bool => $slot['available'] === true)
        );
    }

    public function test_public_booking_uses_selected_staff_for_all_service_lines(): void
    {
        $owner = $this->actingAsOwner();
        $branch = \App\Models\SaloonBranch::query()->create([
            'saloon_id' => $owner->saloon_id,
            'branch_name' => 'Multi Staff Branch',
            'business_address_1' => '123 Test Street',
            'city' => 'Mumbai',
            'state' => 'Maharashtra',
            'area_pincode' => '400001',
            'country' => 'India',
            'is_active' => true,
        ]);

        $weekdaySchedule = [
            'monday' => ['enabled' => true, 'open' => '09:00', 'close' => '17:00'],
            'tuesday' => ['enabled' => true, 'open' => '09:00', 'close' => '17:00'],
            'wednesday' => ['enabled' => true, 'open' => '09:00', 'close' => '17:00'],
            'thursday' => ['enabled' => true, 'open' => '09:00', 'close' => '17:00'],
            'friday' => ['enabled' => true, 'open' => '09:00', 'close' => '17:00'],
            'saturday' => ['enabled' => true, 'open' => '09:00', 'close' => '17:00'],
            'sunday' => ['enabled' => true, 'open' => '09:00', 'close' => '17:00'],
        ];

        $morningOnly = $this->createStaffUser([
            'saloon_id' => $owner->saloon_id,
            'branch_id' => $branch->id,
            'phone' => '+919888888801',
            'weekly_schedule' => array_merge($weekdaySchedule, [
                'monday' => ['enabled' => true, 'open' => '09:00', 'close' => '12:00'],
            ]),
        ]);

        $fullDay = $this->createStaffUser([
            'saloon_id' => $owner->saloon_id,
            'branch_id' => $branch->id,
            'phone' => '+919888888802',
            'weekly_schedule' => $weekdaySchedule,
        ]);

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
                'saloon_id' => $owner->saloon_id,
                'branch_id' => $branch->id,
                'service_id' => $service->id,
                'product_id' => null,
                'price' => (float) $service->default_price,
                'duration_minutes' => (int) $service->duration_minutes,
                'is_active' => true,
            ]);
        }

        $link = SalonBookingLink::query()->create([
            'saloon_id' => $owner->saloon_id,
            'branch_id' => $branch->id,
            'token' => 'multi-staff-booking-token',
            'label' => 'Multi staff test',
            'is_active' => true,
            'created_by' => $owner->id,
        ]);

        $startsAt = now()->next('Monday')->setTime(11, 0);

        $this->postJson('/api/v1/public/book/'.$link->token, [
            'service_ids' => [$cut->id, $color->id],
            'staff_id' => $morningOnly->id,
            'starts_at' => $startsAt->toISOString(),
            'customer_name' => 'Unavailable Guest',
            'customer_phone' => '+919777777770',
        ])->assertStatus(422);

        $response = $this->postJson('/api/v1/public/book/'.$link->token, [
            'service_ids' => [$cut->id, $color->id],
            'staff_id' => $fullDay->id,
            'starts_at' => $startsAt->toISOString(),
            'customer_name' => 'Combo Guest',
            'customer_phone' => '+919777777777',
        ])->assertCreated();

        $appointmentId = $response->json('data.appointment.id');
        $this->assertNotNull($appointmentId);

        $lines = \App\Models\AppointmentService::query()
            ->where('appointment_id', $appointmentId)
            ->orderBy('sort_order')
            ->get(['service_id', 'staff_id']);

        $this->assertCount(2, $lines);
        $this->assertSame($cut->id, $lines[0]->service_id);
        $this->assertSame($color->id, $lines[1]->service_id);
        $this->assertSame($fullDay->id, $lines[0]->staff_id);
        $this->assertSame($fullDay->id, $lines[1]->staff_id);
    }
}
