<?php

namespace Tests\Feature\Customer;

use App\Models\Appointment;
use App\Models\Customer;
use App\Models\CustomerTag;
use App\Models\Saloon;
use App\Models\SaloonBranch;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustomerApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_manage_customers_with_tags_and_visit_history(): void
    {
        $owner = $this->actingAsOwner();
        $saloon = Saloon::query()->findOrFail($owner->saloon_id);
        $branch = SaloonBranch::query()->create([
            'saloon_id' => $saloon->id,
            'branch_name' => 'Main Branch',
            'business_address_1' => '123 Test Street',
            'city' => 'Mumbai',
            'state' => 'Maharashtra',
            'area_pincode' => '400001',
            'country' => 'India',
            'is_active' => true,
        ]);

        $tagResponse = $this->postJson('/api/v1/customer-tags', [
            'name' => 'VIP',
            'color' => 'amber',
        ]);

        $tagResponse
            ->assertCreated()
            ->assertJsonPath('data.tag.name', 'VIP');

        $tagId = (int) $tagResponse->json('data.tag.id');

        $createResponse = $this->postJson('/api/v1/customers', [
            'name' => 'Anita Verma',
            'email' => 'anita@example.com',
            'phone' => '+919876543210',
            'notes' => 'Prefers morning slots',
            'is_active' => true,
            'tag_ids' => [$tagId],
        ]);

        $createResponse
            ->assertCreated()
            ->assertJsonPath('data.customer.name', 'Anita Verma')
            ->assertJsonPath('data.customer.notes', 'Prefers morning slots')
            ->assertJsonCount(1, 'data.customer.tags');

        $customerId = (int) $createResponse->json('data.customer.id');

        Appointment::query()->create([
            'saloon_id' => $saloon->id,
            'branch_id' => $branch->id,
            'customer_id' => $customerId,
            'starts_at' => now()->subDays(3),
            'ends_at' => now()->subDays(3)->addHour(),
            'status' => 'completed',
            'grand_total' => 850.00,
        ]);

        $this->getJson('/api/v1/customers?page=1&per_page=10')
            ->assertOk()
            ->assertJsonPath('data.customers.0.name', 'Anita Verma')
            ->assertJsonPath('data.customers.0.appointments_count', 1);

        $this->getJson("/api/v1/customers/{$customerId}")
            ->assertOk()
            ->assertJsonPath('data.customer.name', 'Anita Verma')
            ->assertJsonPath('data.customer.visit_stats.total_visits', 1)
            ->assertJsonPath('data.customer.visit_stats.total_spent', 850)
            ->assertJsonCount(1, 'data.customer.recent_visits');

        $this->putJson("/api/v1/customers/{$customerId}", [
            'name' => 'Anita V.',
            'email' => 'anita@example.com',
            'phone' => '+919876543210',
            'notes' => 'Updated notes',
            'is_active' => false,
            'tag_ids' => [],
        ])->assertOk()
            ->assertJsonPath('data.customer.name', 'Anita V.')
            ->assertJsonPath('data.customer.is_active', false)
            ->assertJsonCount(0, 'data.customer.tags');

        $this->getJson('/api/v1/customers?segment=returning')
            ->assertOk()
            ->assertJsonCount(0, 'data.customers');

        $this->deleteJson("/api/v1/customers/{$customerId}")
            ->assertOk()
            ->assertJsonPath('message', 'Customer deleted successfully.');

        $this->assertDatabaseMissing('customers', ['id' => $customerId]);
    }

    public function test_customer_tags_are_salon_scoped(): void
    {
        $ownerA = $this->actingAsOwner(['phone' => '+917000000011']);
        $saloonA = (int) $ownerA->saloon_id;

        $tagA = CustomerTag::query()->create([
            'saloon_id' => $saloonA,
            'name' => 'Regular',
            'color' => 'slate',
        ]);

        $saloonB = $this->createSaloon(['name' => 'Other Salon']);
        $this->assignDefaultSubscription($saloonB);
        $tagB = CustomerTag::query()->create([
            'saloon_id' => $saloonB->id,
            'name' => 'VIP',
            'color' => 'amber',
        ]);

        $this->postJson('/api/v1/customers', [
            'name' => 'Cross Tag Test',
            'is_active' => true,
            'tag_ids' => [$tagB->id],
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['tag_ids']);

        $this->postJson('/api/v1/customers', [
            'name' => 'Valid Tag Test',
            'is_active' => true,
            'tag_ids' => [$tagA->id],
        ])->assertCreated()
            ->assertJsonCount(1, 'data.customer.tags');
    }

    public function test_segment_filters_work(): void
    {
        $owner = $this->actingAsOwner(['phone' => '+917000000012']);
        $saloon = Saloon::query()->findOrFail($owner->saloon_id);
        $branch = SaloonBranch::query()->create([
            'saloon_id' => $saloon->id,
            'branch_name' => 'Segment Branch',
            'business_address_1' => '456 Test Street',
            'city' => 'Mumbai',
            'state' => 'Maharashtra',
            'area_pincode' => '400002',
            'country' => 'India',
            'is_active' => true,
        ]);

        $returning = Customer::query()->create([
            'name' => 'Returning Client',
            'is_active' => true,
        ]);
        $returning->attachSaloon($saloon->id);

        foreach ([5, 20] as $daysAgo) {
            Appointment::query()->create([
                'saloon_id' => $saloon->id,
                'branch_id' => $branch->id,
                'customer_id' => $returning->id,
                'starts_at' => now()->subDays($daysAgo),
                'ends_at' => now()->subDays($daysAgo)->addHour(),
                'status' => 'completed',
            ]);
        }

        $lapsed = Customer::query()->create([
            'name' => 'Lapsed Client',
            'is_active' => true,
        ]);
        $lapsed->attachSaloon($saloon->id);

        Appointment::query()->create([
            'saloon_id' => $saloon->id,
            'branch_id' => $branch->id,
            'customer_id' => $lapsed->id,
            'starts_at' => now()->subDays(120),
            'ends_at' => now()->subDays(120)->addHour(),
            'status' => 'completed',
        ]);

        $this->getJson('/api/v1/customers?segment=returning')
            ->assertOk()
            ->assertJsonPath('data.customers.0.name', 'Returning Client');

        $this->getJson('/api/v1/customers?segment=lapsed')
            ->assertOk()
            ->assertJsonPath('data.customers.0.name', 'Lapsed Client');
    }
}
