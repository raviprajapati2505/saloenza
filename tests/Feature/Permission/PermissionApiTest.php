<?php

namespace Tests\Feature\Permission;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PermissionApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_list_permissions(): void
    {
        $user = $this->actingAsOwner(['phone' => '+917333333333']);
        $this->assignDefaultSubscription($user->saloon, 'pro');

        $this->getJson('/api/v1/permissions')
            ->assertOk()
            ->assertJsonPath('message', 'Permissions fetched successfully.')
            ->assertJsonCount(count(\Database\Seeders\ApplicationPermissionSeeder::tenantPermissionCodes()), 'data.permissions')
            ->assertJsonFragment([
                'name' => 'View Roles',
                'code' => 'roles.view',
                'module' => 'roles',
            ]);
    }
}
