<?php

namespace Tests\Feature\Appointment;

use App\Models\Appointment;
use App\Models\Category;
use App\Models\Customer;
use App\Models\Product;
use App\Models\SalonServiceProduct;
use App\Models\Saloon;
use App\Models\SaloonBranch;
use App\Models\Service;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AppointmentCatalogOfferingTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_update_appointment_when_catalog_only_has_branch_variants(): void
    {
        $owner = $this->actingAsOwner(['phone' => '+917000000088']);
        $salon = Saloon::query()->findOrFail($owner->saloon_id);

        $branch = SaloonBranch::query()->create([
            'saloon_id' => $salon->id,
            'branch_name' => 'Catalog Branch',
            'business_address_1' => '1 Road',
            'city' => 'Mumbai',
            'state' => 'MH',
            'area_pincode' => '400001',
            'country' => 'India',
            'is_active' => true,
        ]);

        $customer = Customer::query()->create([
            'name' => 'Catalog Customer',
            'phone' => '+919888777666',
            'is_active' => true,
        ]);
        $customer->attachSaloon($salon->id);

        $category = Category::query()->create(['name' => 'Hair', 'is_active' => true]);
        $service = Service::query()->create([
            'name' => 'Branch Haircut',
            'category_id' => $category->id,
            'default_price' => 649,
            'duration_minutes' => 45,
            'is_active' => true,
        ]);
        $product = Product::query()->create([
            'name' => 'Salon Kit',
            'category_id' => $category->id,
            'default_price' => 100,
            'is_active' => true,
        ]);

        SalonServiceProduct::query()->create([
            'saloon_id' => $salon->id,
            'branch_id' => $branch->id,
            'service_id' => $service->id,
            'product_id' => $product->id,
            'price' => 649,
            'duration_minutes' => 45,
            'is_active' => true,
        ]);

        $starts = now()->addDay()->setTime(11, 0);

        $appointment = Appointment::query()->create([
            'saloon_id' => $salon->id,
            'branch_id' => $branch->id,
            'customer_id' => $customer->id,
            'staff_id' => $owner->id,
            'service_id' => $service->id,
            'starts_at' => $starts,
            'ends_at' => $starts->copy()->addMinutes(45),
            'status' => 'confirmed',
            'type' => 'appointment',
            'price' => 649,
            'grand_total' => 649,
        ]);

        Sanctum::actingAs($owner);

        $this->putJson("/api/v1/appointments/{$appointment->id}", [
            'type' => 'appointment',
            'starts_at' => $starts->toIso8601String(),
            'status' => 'confirmed',
            'customer_id' => $customer->id,
            'branch_id' => $branch->id,
            'services' => [[
                'service_id' => $service->id,
                'price' => 649,
                'duration_minutes' => 45,
            ]],
        ])
            ->assertOk()
            ->assertJsonPath('data.appointment.service_id', $service->id);
    }

    public function test_booking_options_scopes_variants_to_branch_without_duplicates(): void
    {
        $owner = $this->actingAsOwner(['phone' => '+917000000089']);
        $salon = Saloon::query()->findOrFail($owner->saloon_id);

        $branchA = SaloonBranch::query()->create([
            'saloon_id' => $salon->id,
            'branch_name' => 'Branch A',
            'business_address_1' => 'A',
            'city' => 'Mumbai',
            'state' => 'MH',
            'area_pincode' => '400001',
            'country' => 'India',
            'is_active' => true,
        ]);
        $branchB = SaloonBranch::query()->create([
            'saloon_id' => $salon->id,
            'branch_name' => 'Branch B',
            'business_address_1' => 'B',
            'city' => 'Mumbai',
            'state' => 'MH',
            'area_pincode' => '400002',
            'country' => 'India',
            'is_active' => true,
        ]);

        $category = Category::query()->create(['name' => 'Skin', 'is_active' => true]);
        $service = Service::query()->create([
            'name' => 'Facial',
            'category_id' => $category->id,
            'default_price' => 999,
            'duration_minutes' => 60,
            'is_active' => true,
        ]);
        $product = Product::query()->create([
            'name' => 'Serum',
            'category_id' => $category->id,
            'default_price' => 200,
            'is_active' => true,
        ]);

        foreach ([$branchA, $branchB] as $branch) {
            SalonServiceProduct::query()->create([
                'saloon_id' => $salon->id,
                'branch_id' => $branch->id,
                'service_id' => $service->id,
                'product_id' => $product->id,
                'price' => 999,
                'duration_minutes' => 60,
                'is_active' => true,
            ]);
        }

        Sanctum::actingAs($owner);

        $services = collect($this->getJson('/api/v1/appointments/booking-options', [
            'branch_id' => $branchA->id,
        ])->json('data.services'));

        $facial = $services->firstWhere('service_id', $service->id);
        $this->assertNotNull($facial);
        $this->assertCount(1, $facial['products']);
    }
}
