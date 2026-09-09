<?php

namespace Tests\Feature\Queue;

use App\Models\Appointment;
use App\Models\AppointmentService;
use App\Models\Category;
use App\Models\Customer;
use App\Models\Service;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Support\Role\RoleCodes;
use App\Support\Subscription\SubscriptionEntitlements;
use App\Support\Subscription\SubscriptionModules;
use Database\Seeders\ApplicationPermissionSeeder;
use Database\Seeders\SubscriptionPlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class LiveQueueApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(SubscriptionPlanSeeder::class);
    }

    public function test_queue_returns_today_active_appointments_in_order(): void
    {
        $staff = $this->createStaffUser();
        $this->assignQueuePlan($staff->saloon);
        $this->attachPermissions($staff, ApplicationPermissionSeeder::staffPermissionCodes());

        $customer = Customer::query()->create([
            'name' => 'Queue Customer',
            'phone' => '+919333333333',
            'is_active' => true,
        ]);
        $customer->attachSaloon($staff->saloon_id);

        $waiting = Appointment::query()->create([
            'saloon_id' => $staff->saloon_id,
            'branch_id' => $staff->branch_id,
            'customer_id' => $customer->id,
            'staff_id' => $staff->id,
            'starts_at' => now()->addMinutes(10),
            'ends_at' => now()->addMinutes(40),
            'status' => 'confirmed',
            'type' => 'appointment',
            'price' => 500,
            'grand_total' => 500,
        ]);

        $inService = Appointment::query()->create([
            'saloon_id' => $staff->saloon_id,
            'branch_id' => $staff->branch_id,
            'customer_id' => $customer->id,
            'staff_id' => $staff->id,
            'starts_at' => now()->subMinutes(20),
            'ends_at' => now()->addMinutes(10),
            'status' => 'in-progress',
            'type' => 'walk_in',
            'price' => 700,
            'grand_total' => 700,
        ]);

        Sanctum::actingAs($staff);

        $response = $this->getJson('/api/v1/queue/live')
            ->assertOk()
            ->assertJsonPath('data.scope.mode', 'staff')
            ->assertJsonPath('data.summary.in_service', 1)
            ->assertJsonPath('data.summary.waiting', 1);

        $queueIds = collect($response->json('data.queue'))->pluck('id')->all();
        $this->assertSame([$inService->id, $waiting->id], array_slice($queueIds, 0, 2));
    }

    public function test_future_confirmed_appointment_is_upcoming_not_in_service(): void
    {
        $staff = $this->createStaffUser();
        $this->assignQueuePlan($staff->saloon);
        $this->attachPermissions($staff, ApplicationPermissionSeeder::staffPermissionCodes());

        $customer = Customer::query()->create([
            'name' => 'Future Customer',
            'phone' => '+919555555555',
            'is_active' => true,
        ]);
        $customer->attachSaloon($staff->saloon_id);

        $future = Appointment::query()->create([
            'saloon_id' => $staff->saloon_id,
            'branch_id' => $staff->branch_id,
            'customer_id' => $customer->id,
            'staff_id' => $staff->id,
            'starts_at' => now()->addHours(4),
            'ends_at' => now()->addHours(5),
            'status' => 'confirmed',
            'type' => 'appointment',
            'booking_source' => 'self_booking',
            'price' => 800,
            'grand_total' => 800,
        ]);

        Sanctum::actingAs($staff);

        $this->getJson('/api/v1/queue/live')
            ->assertOk()
            ->assertJsonPath('data.summary.in_service', 0)
            ->assertJsonPath('data.summary.upcoming', 1);

        $upcomingIds = collect($this->getJson('/api/v1/queue/live')->json('data.sections.upcoming'))->pluck('id')->all();
        $this->assertContains($future->id, $upcomingIds);
    }

    public function test_staff_only_sees_assigned_queue_items_and_service_lines(): void
    {
        $staff = $this->createStaffUser();
        $otherStaff = User::factory()->create([
            'saloon_id' => $staff->saloon_id,
            'branch_id' => $staff->branch_id,
            'role_id' => $staff->role_id,
            'is_active' => true,
            'phone' => '+919444444444',
        ]);

        $this->assignQueuePlan($staff->saloon);
        $this->attachPermissions($staff, ApplicationPermissionSeeder::staffPermissionCodes());

        $customer = Customer::query()->create([
            'name' => 'Shared Customer',
            'phone' => '+919666666666',
            'is_active' => true,
        ]);
        $customer->attachSaloon($staff->saloon_id);

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

        $shared = Appointment::query()->create([
            'saloon_id' => $staff->saloon_id,
            'branch_id' => $staff->branch_id,
            'customer_id' => $customer->id,
            'staff_id' => $otherStaff->id,
            'starts_at' => now()->addMinutes(15),
            'ends_at' => now()->addMinutes(75),
            'status' => 'confirmed',
            'type' => 'appointment',
            'price' => 1700,
            'grand_total' => 1700,
        ]);

        AppointmentService::query()->create([
            'appointment_id' => $shared->id,
            'service_id' => $cut->id,
            'staff_id' => $staff->id,
            'price' => 500,
            'duration_minutes' => 30,
            'starts_at' => $shared->starts_at,
            'ends_at' => $shared->starts_at->copy()->addMinutes(30),
            'sort_order' => 0,
        ]);

        AppointmentService::query()->create([
            'appointment_id' => $shared->id,
            'service_id' => $color->id,
            'staff_id' => $otherStaff->id,
            'price' => 1200,
            'duration_minutes' => 60,
            'starts_at' => $shared->starts_at->copy()->addMinutes(30),
            'ends_at' => $shared->ends_at,
            'sort_order' => 1,
        ]);

        $otherOnly = Appointment::query()->create([
            'saloon_id' => $staff->saloon_id,
            'branch_id' => $staff->branch_id,
            'customer_id' => $customer->id,
            'staff_id' => $otherStaff->id,
            'starts_at' => now()->addMinutes(90),
            'ends_at' => now()->addMinutes(120),
            'status' => 'confirmed',
            'type' => 'appointment',
            'price' => 500,
            'grand_total' => 500,
        ]);

        Sanctum::actingAs($staff);

        $response = $this->getJson('/api/v1/queue/live')->assertOk();

        $queueIds = collect($response->json('data.queue'))->pluck('id')->all();
        $this->assertContains($shared->id, $queueIds);
        $this->assertNotContains($otherOnly->id, $queueIds);

        $sharedItem = collect($response->json('data.queue'))->firstWhere('id', $shared->id);
        $this->assertSame(['Cut'], $sharedItem['services']);
        $this->assertSame($staff->id, $sharedItem['staff']['id']);
        $this->assertSame($staff->name, $sharedItem['staff']['name']);
    }

    public function test_branch_manager_sees_full_branch_queue_with_view_all_permission(): void
    {
        $staff = $this->createStaffUser();
        $manager = User::factory()->create([
            'saloon_id' => $staff->saloon_id,
            'branch_id' => $staff->branch_id,
            'role_id' => \App\Models\Role::findByCode(RoleCodes::SALON_BRANCH_MANAGER)->id,
            'is_active' => true,
            'phone' => '+919777777777',
        ]);

        $this->assignQueuePlan($staff->saloon);

        $customer = Customer::query()->create([
            'name' => 'Branch Customer',
            'phone' => '+919888888888',
            'is_active' => true,
        ]);
        $customer->attachSaloon($staff->saloon_id);

        $staffAppt = Appointment::query()->create([
            'saloon_id' => $staff->saloon_id,
            'branch_id' => $staff->branch_id,
            'customer_id' => $customer->id,
            'staff_id' => $staff->id,
            'starts_at' => now()->addMinutes(20),
            'ends_at' => now()->addMinutes(50),
            'status' => 'confirmed',
            'type' => 'appointment',
            'price' => 500,
            'grand_total' => 500,
        ]);

        $managerAppt = Appointment::query()->create([
            'saloon_id' => $staff->saloon_id,
            'branch_id' => $staff->branch_id,
            'customer_id' => $customer->id,
            'staff_id' => $manager->id,
            'starts_at' => now()->addMinutes(30),
            'ends_at' => now()->addMinutes(60),
            'status' => 'confirmed',
            'type' => 'appointment',
            'price' => 600,
            'grand_total' => 600,
        ]);

        Sanctum::actingAs($manager);

        $response = $this->getJson('/api/v1/queue/live')
            ->assertOk()
            ->assertJsonPath('data.scope.mode', 'branch');

        $queueIds = collect($response->json('data.queue'))->pluck('id')->all();
        $this->assertContains($staffAppt->id, $queueIds);
        $this->assertContains($managerAppt->id, $queueIds);
    }

    public function test_plan_without_queue_module_blocks_live_queue_api(): void
    {
        $staff = $this->createStaffUser();
        $this->attachPermissions($staff, ApplicationPermissionSeeder::staffPermissionCodes());

        Sanctum::actingAs($staff);

        $this->getJson('/api/v1/queue/live')
            ->assertForbidden()
            ->assertJsonPath('required_modules.0', SubscriptionModules::QUEUE);
    }

    public function test_waiting_walk_in_appears_once_in_serving_order(): void
    {
        $staff = $this->createStaffUser();
        $this->assignQueuePlan($staff->saloon);
        $this->attachPermissions($staff, ApplicationPermissionSeeder::staffPermissionCodes());

        $customer = Customer::query()->create([
            'name' => 'Vikram Singh',
            'phone' => '+919222222204',
            'is_active' => true,
        ]);
        $customer->attachSaloon($staff->saloon_id);

        $walkIn = Appointment::query()->create([
            'saloon_id' => $staff->saloon_id,
            'branch_id' => $staff->branch_id,
            'customer_id' => $customer->id,
            'staff_id' => $staff->id,
            'starts_at' => now()->subMinutes(10),
            'ends_at' => now()->addMinutes(50),
            'status' => 'confirmed',
            'type' => 'walk_in',
            'booking_source' => 'walk_in',
            'price' => 2699,
            'grand_total' => 2699,
        ]);

        Sanctum::actingAs($staff);

        $response = $this->getJson('/api/v1/queue/live')->assertOk();

        $queueIds = collect($response->json('data.queue'))->pluck('id')->all();
        $this->assertSame([$walkIn->id], array_values(array_filter($queueIds, fn (int $id): bool => $id === $walkIn->id)));
        $this->assertCount(count(array_unique($queueIds)), $queueIds);
        $this->assertSame(0, $response->json('data.summary.waiting'));
        $this->assertSame(1, $response->json('data.summary.walk_ins'));
        $this->assertSame(1, $response->json('data.summary.walk_ins_total'));

        $walkInItem = collect($response->json('data.queue'))->firstWhere('id', $walkIn->id);
        $this->assertSame('walk_in_waiting', $walkInItem['queue_lane']);
    }

    public function test_in_progress_walk_in_is_not_listed_in_walk_ins_section(): void
    {
        $staff = $this->createStaffUser();
        $this->assignQueuePlan($staff->saloon);
        $this->attachPermissions($staff, ApplicationPermissionSeeder::staffPermissionCodes());

        $customer = Customer::query()->create([
            'name' => 'Active Walk-in',
            'phone' => '+919222222205',
            'is_active' => true,
        ]);
        $customer->attachSaloon($staff->saloon_id);

        $walkIn = Appointment::query()->create([
            'saloon_id' => $staff->saloon_id,
            'branch_id' => $staff->branch_id,
            'customer_id' => $customer->id,
            'staff_id' => $staff->id,
            'starts_at' => now()->subMinutes(15),
            'ends_at' => now()->addMinutes(45),
            'status' => 'in-progress',
            'type' => 'walk_in',
            'price' => 1500,
            'grand_total' => 1500,
        ]);

        Sanctum::actingAs($staff);

        $response = $this->getJson('/api/v1/queue/live')->assertOk();

        $this->assertSame(1, $response->json('data.summary.in_service'));
        $this->assertSame(0, $response->json('data.summary.walk_ins'));
        $this->assertSame(1, $response->json('data.summary.walk_ins_total'));

        $walkInSectionIds = collect($response->json('data.sections.walk_ins'))->pluck('id')->all();
        $this->assertNotContains($walkIn->id, $walkInSectionIds);

        $queueIds = collect($response->json('data.queue'))->pluck('id')->all();
        $this->assertSame([$walkIn->id], $queueIds);
        $this->assertSame('in_service', collect($response->json('data.queue'))->first()['queue_lane']);
    }

    /**
     * @param  list<string>  $codes
     */
    private function attachPermissions(User $user, array $codes): void
    {
        $this->seed(ApplicationPermissionSeeder::class);
        $role = \App\Models\Role::findByCode(\App\Support\Role\RoleCodes::SALON_STAFF);
        $role->permissions()->sync(
            \App\Models\Permission::query()->whereIn('code', $codes)->pluck('id'),
        );
        $user->update(['role_id' => $role->id]);
    }

    private function assignQueuePlan(\App\Models\Saloon $saloon): void
    {
        $plan = SubscriptionPlan::query()->where('slug', 'basic')->firstOrFail();
        app(SubscriptionEntitlements::class)->assignPlan($saloon, $plan, startTrial: false);
    }
}
