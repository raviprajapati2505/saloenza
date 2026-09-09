<?php

namespace Tests\Feature\Admin;

use App\Mail\SalonWelcomeMail;
use App\Models\Category;
use App\Models\Product;
use App\Models\Role;
use App\Models\Saloon;
use App\Models\Service;
use App\Models\User;
use App\Support\Role\RoleCodes;
use Database\Seeders\SubscriptionPlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminOnboardingApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedReferenceData();
        $this->seed(SubscriptionPlanSeeder::class);
    }

    public function test_super_admin_can_onboard_salon(): void
    {
        Mail::fake();

        $superAdmin = $this->actingAsSystemAdmin([
            'email' => 'admin.onboard@salonos.com',
        ]);

        $category = Category::query()->create([
            'name' => 'Hair',
            'is_active' => true,
        ]);

        $service = Service::query()->create([
            'name' => 'Haircut',
            'category_id' => $category->id,
            'default_price' => 500,
            'duration_minutes' => 45,
        ]);
        $product = Product::query()->create([
            'name' => 'Shampoo',
            'category_id' => $category->id,
            'is_active' => true,
        ]);

        Sanctum::actingAs($superAdmin);

        $response = $this->postJson('/api/v1/admin/onboarding', [
            'saloon' => [
                'business_name' => 'Glow Studio',
                'payment_type' => 'online',
                'payment_amount' => 999.00,
                'transaction_id' => 'TXN-001',
                'is_active' => true,
            ],
            'branch' => [
                'branch_name' => 'Main Branch',
                'business_address_1' => '42 Market Street',
                'business_address_2' => 'Near City Mall',
                'city' => 'Mumbai',
                'state' => 'Maharashtra',
                'area_pincode' => '400001',
                'country' => 'India',
                'is_active' => true,
            ],
            'user' => [
                'firstname' => 'Ravi',
                'lastname' => 'Owner',
                'email' => 'owner@glowstudio.com',
                'phone' => '+91 9876543210',
                'password' => 'Password@123',
                'password_confirmation' => 'Password@123',
                'is_active' => true,
            ],
            'service_products' => [
                [
                    'service_id' => $service->id,
                    'product_id' => $product->id,
                    'price' => 550.00,
                    'duration_minutes' => 45,
                    'is_active' => true,
                ],
            ],
        ]);

        $saloonOwnerRole = Role::findByCode(RoleCodes::SALON_FRANCHISE_OWNER);

        $response->assertCreated();
        $response->assertJsonPath('message', 'Salon onboarded successfully.');
        $response->assertJsonPath('data.saloon.business_name', 'Glow Studio');
        $response->assertJsonPath('data.branch.branch_name', 'Main Branch');
        $response->assertJsonPath('data.user.firstname', 'Ravi');
        $response->assertJsonPath('data.user.role_id', $saloonOwnerRole->id);
        $response->assertJsonPath('data.service_products.0.price', '550.00');

        $this->assertDatabaseHas('saloons', [
            'name' => 'Glow Studio',
        ]);

        $referralCode = (string) $response->json('data.saloon.referral_code');
        $this->assertNotEmpty($referralCode);
        $this->assertStringStartsWith('GLOW', $referralCode);
        $this->assertSame(10, strlen($referralCode));
        $this->assertSame($referralCode, Saloon::query()->where('name', 'Glow Studio')->value('referral_code'));
        $this->assertDatabaseHas('saloon_branches', [
            'branch_name' => 'Main Branch',
            'area_pincode' => '400001',
        ]);
        $this->assertDatabaseHas('users', [
            'email' => 'owner@glowstudio.com',
            'firstname' => 'Ravi',
            'lastname' => 'Owner',
            'role_id' => $saloonOwnerRole->id,
        ]);
        $this->assertDatabaseHas('salon_service_products', [
            'service_id' => $service->id,
            'product_id' => $product->id,
        ]);

        Mail::assertSent(SalonWelcomeMail::class, function (SalonWelcomeMail $mail): bool {
            return $mail->hasTo('owner@glowstudio.com');
        });
    }

    public function test_super_admin_can_onboard_salon_with_custom_service_product(): void
    {
        Mail::fake();
        Category::query()->create([
            'name' => 'General',
            'is_active' => true,
        ]);

        $superAdmin = $this->actingAsSystemAdmin([
            'email' => 'admin.custom.onboard@salonos.com',
        ]);

        Sanctum::actingAs($superAdmin);

        $this->assertDatabaseCount('services', 0);
        $this->assertDatabaseCount('products', 0);

        $response = $this->postJson('/api/v1/admin/onboarding', [
            'saloon' => [
                'business_name' => 'Custom Cuts',
                'payment_type' => 'Monthly',
                'payment_amount' => 499.00,
                'transaction_id' => 'TXN-CUSTOM-001',
                'is_active' => true,
            ],
            'branch' => [
                'branch_name' => 'Flagship',
                'business_address_1' => '10 Main Road',
                'business_address_2' => null,
                'city' => 'Bengaluru',
                'state' => 'Karnataka',
                'area_pincode' => '560001',
                'country' => 'India',
                'is_active' => true,
            ],
            'user' => [
                'firstname' => 'Custom',
                'lastname' => 'Owner',
                'email' => 'owner@customcuts.com',
                'phone' => '+91 9988776655',
                'password' => 'Password@123',
                'password_confirmation' => 'Password@123',
                'is_active' => true,
            ],
            'service_products' => [
                [
                    'service_name' => 'Beard Trim',
                    'product_name' => 'Standard',
                    'price' => 250.00,
                    'duration_minutes' => 20,
                    'is_active' => true,
                ],
            ],
        ]);

        $response->assertCreated();
        $response->assertJsonPath('data.saloon.business_name', 'Custom Cuts');

        $this->assertDatabaseHas('services', [
            'name' => 'Beard Trim',
        ]);
        $this->assertDatabaseHas('products', [
            'name' => 'Standard',
        ]);

        $serviceId = Service::query()->where('name', 'Beard Trim')->value('id');
        $productId = Product::query()->where('name', 'Standard')->value('id');

        $this->assertDatabaseHas('salon_service_products', [
            'service_id' => $serviceId,
            'product_id' => $productId,
            'price' => 250.00,
            'duration_minutes' => 20,
        ]);

        Mail::assertSent(SalonWelcomeMail::class, function (SalonWelcomeMail $mail): bool {
            return $mail->hasTo('owner@customcuts.com');
        });
    }

    public function test_super_admin_can_show_onboarding_record(): void
    {
        $this->actingAsSystemAdmin();
        $owner = $this->createOwnerUser([
            'email' => 'owner-show@test.com',
            'firstname' => 'Show',
            'lastname' => 'Owner',
            'phone' => '9876543210',
        ]);
        $saloon = $owner->saloon;
        $branch = $saloon->branches()->create([
            'branch_name' => 'Main Branch',
            'business_address_1' => '42 Market Street',
            'city' => 'Mumbai',
            'state' => 'Maharashtra',
            'area_pincode' => '400001',
            'country' => 'India',
            'is_active' => true,
        ]);
        $owner->update(['branch_id' => $branch->id]);

        $response = $this->getJson("/api/v1/admin/onboarding/{$saloon->id}");

        $response->assertOk();
        $response->assertJsonPath('message', 'Onboarding record fetched successfully.');
        $response->assertJsonPath('data.saloon.business_name', $saloon->name);
        $response->assertJsonPath('data.branch.branch_name', 'Main Branch');
        $response->assertJsonPath('data.user.email', 'owner-show@test.com');
    }

    public function test_super_admin_can_update_onboarding_record(): void
    {
        $this->actingAsSystemAdmin();
        $owner = $this->createOwnerUser([
            'email' => 'owner-update@test.com',
            'firstname' => 'Old',
            'lastname' => 'Owner',
            'phone' => '9876543210',
        ]);
        $saloon = $owner->saloon;
        $branch = $saloon->branches()->create([
            'branch_name' => 'Old Branch',
            'business_address_1' => 'Old Street',
            'city' => 'Mumbai',
            'state' => 'Maharashtra',
            'area_pincode' => '400001',
            'country' => 'India',
            'is_active' => true,
        ]);
        $owner->update(['branch_id' => $branch->id]);

        $response = $this->putJson("/api/v1/admin/onboarding/{$saloon->id}", [
            'saloon' => [
                'business_name' => 'Updated Salon',
                'payment_type' => 'Yearly',
                'payment_amount' => 1200,
                'transaction_id' => 'TXN-UPDATED',
                'is_active' => true,
                'referral_code' => 'UPDATED',
            ],
            'branch' => [
                'id' => $branch->id,
                'branch_name' => 'Updated Branch',
                'business_address_1' => 'New Street',
                'business_address_2' => null,
                'city' => 'Pune',
                'state' => 'Maharashtra',
                'area_pincode' => '411001',
                'country' => 'India',
                'is_active' => true,
            ],
            'user' => [
                'id' => $owner->id,
                'firstname' => 'New',
                'lastname' => 'Owner',
                'email' => 'new-owner@test.com',
                'phone' => '9123456789',
                'is_active' => true,
            ],
            'service_products' => [],
        ]);

        $response->assertOk();
        $response->assertJsonPath('message', 'Onboarding record updated successfully.');
        $response->assertJsonPath('data.saloon.business_name', 'Updated Salon');
        $response->assertJsonPath('data.branch.branch_name', 'Updated Branch');
        $response->assertJsonPath('data.user.email', 'new-owner@test.com');

        $this->assertDatabaseHas('saloons', ['id' => $saloon->id, 'name' => 'Updated Salon']);
        $this->assertDatabaseHas('saloon_branches', ['id' => $branch->id, 'branch_name' => 'Updated Branch', 'city' => 'Pune']);
        $this->assertDatabaseHas('users', ['id' => $owner->id, 'email' => 'new-owner@test.com', 'firstname' => 'New']);
    }

    public function test_super_admin_can_update_onboarding_keeping_same_owner_email_without_user_id(): void
    {
        $this->actingAsSystemAdmin();
        $owner = $this->createOwnerUser([
            'email' => 'same-owner@test.com',
            'firstname' => 'Same',
            'lastname' => 'Owner',
            'phone' => '9876543210',
        ]);
        $saloon = $owner->saloon;
        $branch = $saloon->branches()->create([
            'branch_name' => 'Main Branch',
            'business_address_1' => '42 Market Street',
            'city' => 'Mumbai',
            'state' => 'Maharashtra',
            'area_pincode' => '400001',
            'country' => 'India',
            'is_active' => true,
        ]);
        $owner->update(['branch_id' => $branch->id]);

        $response = $this->putJson("/api/v1/admin/onboarding/{$saloon->id}", [
            'saloon' => [
                'business_name' => 'Same Email Salon',
                'payment_type' => 'Monthly',
                'payment_amount' => 500,
                'transaction_id' => 'TXN-SAME',
                'is_active' => true,
            ],
            'branch' => [
                'branch_name' => 'Main Branch',
                'business_address_1' => '42 Market Street',
                'business_address_2' => null,
                'city' => 'Mumbai',
                'state' => 'Maharashtra',
                'area_pincode' => '400001',
                'country' => 'India',
                'is_active' => true,
            ],
            'user' => [
                'firstname' => 'Same',
                'lastname' => 'Owner',
                'email' => 'same-owner@test.com',
                'phone' => '9876543210',
                'is_active' => true,
            ],
            'service_products' => [],
        ]);

        $response->assertOk();
        $response->assertJsonPath('data.user.email', 'same-owner@test.com');
        $this->assertDatabaseHas('users', ['id' => $owner->id, 'email' => 'same-owner@test.com']);
    }

    public function test_non_super_admin_cannot_access_admin_onboarding(): void
    {
        $user = $this->createOwnerUser();

        Sanctum::actingAs($user);

        $response = $this->postJson('/api/v1/admin/onboarding', [
            'saloon' => [
                'business_name' => 'Glow Studio',
                'payment_type' => 'online',
                'payment_amount' => 999.00,
                'is_active' => true,
            ],
            'branch' => [
                'branch_name' => 'Main Branch',
                'business_address_1' => '42 Market Street',
                'city' => 'Mumbai',
                'state' => 'Maharashtra',
                'area_pincode' => '400001',
                'is_active' => true,
            ],
            'user' => [
                'firstname' => 'Ravi',
                'lastname' => 'Owner',
                'email' => 'owner@glowstudio.com',
                'phone' => '+91 9876543210',
                'is_active' => true,
            ],
        ]);

        $response->assertForbidden();
        $response->assertJsonPath('message', 'Forbidden. Platform permission required.');
    }

    public function test_super_admin_can_list_onboarding_records(): void
    {
        $superAdmin = $this->actingAsSystemAdmin();
        $owner = $this->createOwnerUser([
            'email' => 'pending-owner@test.com',
            'onboarding_completed_at' => null,
        ]);

        $response = $this->getJson('/api/v1/admin/onboarding');

        $response->assertOk();
        $response->assertJsonPath('message', 'Onboarding records fetched successfully.');
        $response->assertJsonStructure([
            'data' => [
                'onboardings',
                'summary' => ['total', 'completed', 'pending', 'no_owner'],
            ],
        ]);

        $response->assertJsonFragment([
            'business_name' => 'Test Saloon',
            'onboarding_status' => 'pending',
            'email' => 'pending-owner@test.com',
        ]);
    }

    public function test_super_admin_can_filter_onboarding_by_status(): void
    {
        $this->actingAsSystemAdmin();

        $this->createOwnerUser([
            'email' => 'pending@test.com',
            'onboarding_completed_at' => null,
        ]);

        $this->createOwnerUser([
            'email' => 'done@test.com',
            'onboarding_completed_at' => now(),
        ]);

        $response = $this->getJson('/api/v1/admin/onboarding?onboarding_status=completed');

        $response->assertOk();
        $this->assertGreaterThanOrEqual(1, (int) $response->json('data.summary.completed'));
        $emails = collect($response->json('data.onboardings'))
            ->map(fn (array $row) => $row['owner']['email'] ?? null)
            ->filter()
            ->values()
            ->all();
        $this->assertContains('done@test.com', $emails);
        foreach ($response->json('data.onboardings') as $row) {
            $this->assertSame('completed', $row['onboarding_status']);
        }
    }

    public function test_super_admin_can_mark_owner_onboarding_complete(): void
    {
        $this->actingAsSystemAdmin();
        $owner = $this->createOwnerUser([
            'onboarding_completed_at' => null,
        ]);

        $response = $this->postJson("/api/v1/admin/onboarding/owners/{$owner->id}/complete");

        $response->assertOk();
        $response->assertJsonPath('message', 'Owner onboarding marked as complete.');
        $response->assertJsonPath('data.owner.should_onboard', false);

        $this->assertNotNull($owner->fresh()->onboarding_completed_at);
    }

    public function test_super_admin_can_reset_owner_onboarding(): void
    {
        $this->actingAsSystemAdmin();
        $owner = $this->createOwnerUser([
            'onboarding_completed_at' => now(),
        ]);

        $response = $this->postJson("/api/v1/admin/onboarding/owners/{$owner->id}/reset");

        $response->assertOk();
        $response->assertJsonPath('message', 'Owner onboarding reset successfully.');
        $response->assertJsonPath('data.owner.should_onboard', true);

        $this->assertNull($owner->fresh()->onboarding_completed_at);
    }

    public function test_non_owner_cannot_be_managed_via_onboarding_actions(): void
    {
        $this->actingAsSystemAdmin();
        $staffRole = Role::findByCode(RoleCodes::SALON_STAFF);
        $saloon = $this->createSaloon();
        $staff = User::factory()->create([
            'saloon_id' => $saloon->id,
            'role_id' => $staffRole->id,
        ]);

        $this->postJson("/api/v1/admin/onboarding/owners/{$staff->id}/complete")
            ->assertStatus(422);

        $this->postJson("/api/v1/admin/onboarding/owners/{$staff->id}/reset")
            ->assertStatus(422);
    }
}
