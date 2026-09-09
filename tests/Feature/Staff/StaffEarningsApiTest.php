<?php

namespace Tests\Feature\Staff;

use App\Models\Appointment;
use App\Models\AppointmentService;
use App\Models\Category;
use App\Models\Customer;
use App\Models\SalonServiceProduct;
use App\Models\Service;
use App\Models\User;
use App\Support\Role\RoleCodes;
use Database\Seeders\ApplicationPermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class StaffEarningsApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_staff_can_view_own_earnings_with_commission(): void
    {
        $staff = $this->createStaffUser([
            'commission_rate' => 20,
        ]);
        $this->attachPermissions($staff, ApplicationPermissionSeeder::staffPermissionCodes());

        $customer = Customer::query()->create([
            'name' => 'Earnings Customer',
            'phone' => '+919111111111',
            'is_active' => true,
        ]);
        $customer->attachSaloon($staff->saloon_id);

        $category = Category::query()->create(['name' => 'Hair', 'is_active' => true]);
        $service = Service::query()->create([
            'name' => 'Cut',
            'category_id' => $category->id,
            'default_price' => 500,
            'duration_minutes' => 30,
            'is_active' => true,
        ]);

        SalonServiceProduct::query()->create([
            'saloon_id' => $staff->saloon_id,
            'branch_id' => $staff->branch_id,
            'service_id' => $service->id,
            'product_id' => null,
            'price' => 1000,
            'duration_minutes' => 30,
            'is_active' => true,
        ]);

        $appointment = Appointment::query()->create([
            'saloon_id' => $staff->saloon_id,
            'branch_id' => $staff->branch_id,
            'customer_id' => $customer->id,
            'staff_id' => $staff->id,
            'service_id' => $service->id,
            'starts_at' => now()->setTime(10, 0),
            'ends_at' => now()->setTime(10, 30),
            'status' => 'completed',
            'type' => 'appointment',
            'price' => 1000,
            'grand_total' => 1000,
        ]);

        AppointmentService::query()->create([
            'appointment_id' => $appointment->id,
            'service_id' => $service->id,
            'staff_id' => $staff->id,
            'price' => 1000,
            'duration_minutes' => 30,
            'starts_at' => $appointment->starts_at,
            'ends_at' => $appointment->ends_at,
            'sort_order' => 0,
        ]);

        Sanctum::actingAs($staff);

        $this->getJson('/api/v1/staff/earnings?preset=today')
            ->assertOk()
            ->assertJsonPath('data.report.summary.revenue_generated', 1000)
            ->assertJsonPath('data.report.summary.commission_earned', 200)
            ->assertJsonPath('data.report.summary.services_performed', 1);
    }

    public function test_staff_cannot_view_other_staff_earnings_without_staff_view(): void
    {
        $staff = $this->createStaffUser();
        $this->attachPermissions($staff, ApplicationPermissionSeeder::staffPermissionCodes());

        $other = User::factory()->create([
            'saloon_id' => $staff->saloon_id,
            'branch_id' => $staff->branch_id,
            'role_id' => $staff->role_id,
            'is_active' => true,
            'phone' => '+919222222222',
        ]);

        Sanctum::actingAs($staff);

        $this->getJson('/api/v1/staff/earnings?staff_id='.$other->id)
            ->assertForbidden();
    }

    /**
     * @param  list<string>  $codes
     */
    private function attachPermissions(User $user, array $codes): void
    {
        $this->seed(ApplicationPermissionSeeder::class);
        $role = \App\Models\Role::findByCode(RoleCodes::SALON_STAFF)
            ?? throw new \RuntimeException('Missing staff role.');
        $role->permissions()->sync(
            \App\Models\Permission::query()->whereIn('code', $codes)->pluck('id'),
        );
        $user->update(['role_id' => $role->id]);
    }
}
