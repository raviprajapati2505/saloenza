<?php

namespace Tests\Feature\Customer;

use App\Models\Category;
use App\Models\Customer;
use App\Models\Permission;
use App\Models\Role;
use App\Models\SalonServiceProduct;
use App\Models\Service;
use App\Models\User;
use App\Services\Appointment\AppointmentBookingService;
use App\Support\Role\RoleCodes;
use Database\Seeders\ApplicationPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CustomerContactPrivacyTest extends TestCase
{
    use RefreshDatabase;

    public function test_staff_cannot_see_contacts_for_customers_they_did_not_add(): void
    {
        $staff = $this->createStaffUser(['phone' => '+917000000201']);
        $this->attachStaffPermissions($staff);

        $ownerCustomer = Customer::query()->create([
            'name' => 'Owner Added',
            'email' => 'owner.added@example.com',
            'phone' => '+919111111111',
            'is_active' => true,
            'created_by' => null,
        ]);
        $ownerCustomer->attachSaloon((int) $staff->saloon_id);

        $ownCustomer = Customer::query()->create([
            'name' => 'Staff Added',
            'email' => 'staff.added@example.com',
            'phone' => '+919222222222',
            'is_active' => true,
            'created_by' => $staff->id,
        ]);
        $ownCustomer->attachSaloon((int) $staff->saloon_id);

        Sanctum::actingAs($staff);

        $this->getJson("/api/v1/customers/{$ownerCustomer->id}")
            ->assertOk()
            ->assertJsonPath('data.customer.name', 'Owner Added')
            ->assertJsonPath('data.customer.phone', null)
            ->assertJsonPath('data.customer.email', null)
            ->assertJsonPath('data.customer.can_view_contact', false)
            ->assertJsonPath('data.customer.has_phone', true)
            ->assertJsonPath('data.customer.has_email', true);

        $this->getJson("/api/v1/customers/{$ownCustomer->id}")
            ->assertOk()
            ->assertJsonPath('data.customer.phone', '+919222222222')
            ->assertJsonPath('data.customer.email', 'staff.added@example.com')
            ->assertJsonPath('data.customer.can_view_contact', true);
    }

    public function test_owner_and_branch_manager_can_see_all_contacts(): void
    {
        $owner = $this->actingAsOwner(['phone' => '+917000000202']);

        $customer = Customer::query()->create([
            'name' => 'Private Client',
            'email' => 'private@example.com',
            'phone' => '+919333333333',
            'is_active' => true,
            'created_by' => null,
        ]);
        $customer->attachSaloon((int) $owner->saloon_id);

        $this->getJson("/api/v1/customers/{$customer->id}")
            ->assertOk()
            ->assertJsonPath('data.customer.phone', '+919333333333')
            ->assertJsonPath('data.customer.can_view_contact', true);

        $managerRole = Role::findByCode(RoleCodes::SALON_BRANCH_MANAGER);
        $manager = User::factory()->create([
            'email' => 'privacy.manager@test.com',
            'phone' => '+917000000203',
            'role_id' => $managerRole->id,
            'saloon_id' => $owner->saloon_id,
            'is_active' => true,
        ]);
        Sanctum::actingAs($manager);

        $this->getJson("/api/v1/customers/{$customer->id}")
            ->assertOk()
            ->assertJsonPath('data.customer.phone', '+919333333333')
            ->assertJsonPath('data.customer.can_view_contact', true);
    }

    public function test_creating_customer_sets_created_by_and_supports_occasions(): void
    {
        $staff = $this->createStaffUser(['phone' => '+917000000204']);
        $this->attachStaffPermissions($staff);
        Sanctum::actingAs($staff);

        $response = $this->postJson('/api/v1/customers', [
            'name' => 'Birthday Guest',
            'email' => 'bday@example.com',
            'phone' => '+919444444444',
            'birthday' => '1995-08-24',
            'anniversary' => '2018-03-01',
            'is_active' => true,
        ])->assertCreated();

        $customerId = (int) $response->json('data.customer.id');
        $customer = Customer::query()->findOrFail($customerId);

        $this->assertSame($staff->id, (int) $customer->created_by);
        $this->assertSame('1995-08-24', $customer->birthday?->toDateString());
        $this->assertSame('2018-03-01', $customer->anniversary?->toDateString());

        $response
            ->assertJsonPath('data.customer.birthday', '1995-08-24')
            ->assertJsonPath('data.customer.can_view_contact', true)
            ->assertJsonPath('data.customer.phone', '+919444444444');
    }

    public function test_staff_cannot_search_hidden_contacts_but_can_search_their_own_customer_contact(): void
    {
        $staff = $this->createStaffUser(['phone' => '+917000000205']);
        $this->attachStaffPermissions($staff);

        $hidden = Customer::query()->create([
            'name' => 'Hidden Search',
            'email' => 'hidden.search@example.com',
            'phone' => '+919555000001',
            'is_active' => true,
            'created_by' => null,
        ]);
        $hidden->attachSaloon((int) $staff->saloon_id);

        $own = Customer::query()->create([
            'name' => 'Own Search',
            'email' => 'own.search@example.com',
            'phone' => '+919555000002',
            'is_active' => true,
            'created_by' => $staff->id,
        ]);
        $own->attachSaloon((int) $staff->saloon_id);

        Sanctum::actingAs($staff);

        $this->getJson('/api/v1/customers?search=%2B919555000001')
            ->assertOk()
            ->assertJsonCount(0, 'data.customers');

        $this->getJson('/api/v1/customers?search=%2B919555000002')
            ->assertOk()
            ->assertJsonCount(1, 'data.customers')
            ->assertJsonPath('data.customers.0.id', $own->id);

        $this->getJson('/api/v1/appointments/customers?search=%2B919555000001')
            ->assertOk()
            ->assertJsonCount(0, 'data.customers');

        $this->getJson('/api/v1/appointments/customers?search=%2B919555000002')
            ->assertOk()
            ->assertJsonCount(1, 'data.customers')
            ->assertJsonPath('data.customers.0.id', $own->id);
    }

    public function test_resolve_customer_id_backfills_email_for_existing_customer_found_by_phone(): void
    {
        $staff = $this->createStaffUser(['phone' => '+917000000206']);

        $customer = Customer::query()->create([
            'name' => 'Email Pending',
            'phone' => '+919666000001',
            'email' => null,
            'is_active' => true,
            'created_by' => $staff->id,
        ]);
        $customer->attachSaloon((int) $staff->saloon_id);

        $resolved = app(AppointmentBookingService::class)->resolveCustomerId([
            'customer_name' => 'Email Pending',
            'customer_phone' => '+919666000001',
            'customer_email' => 'filled@example.com',
        ], (int) $staff->saloon_id, null, $staff);

        $this->assertSame($customer->id, $resolved);
        $this->assertSame('filled@example.com', $customer->fresh()->email);
    }

    private function attachStaffPermissions(User $user): void
    {
        $this->seed(ApplicationPermissionSeeder::class);
        $role = Role::findByCode(RoleCodes::SALON_STAFF)
            ?? throw new \RuntimeException('Missing staff role.');
        $role->permissions()->sync(
            Permission::query()
                ->whereIn('code', ApplicationPermissionSeeder::staffPermissionCodes())
                ->pluck('id'),
        );
        $user->update(['role_id' => $role->id]);
        $user->unsetRelation('role');
    }
}
