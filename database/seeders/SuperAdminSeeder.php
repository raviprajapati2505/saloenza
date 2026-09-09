<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\User;
use App\Support\Role\RoleCodes;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class SuperAdminSeeder extends Seeder
{
    public function run(): void
    {
        $platformRole = Role::findByCode(RoleCodes::PLATFORM_SUPER_ADMIN);

        $user = User::query()->firstOrNew([
            'email' => 'admin@salonos.com',
        ]);

        $user->fill([
            'name' => 'Super Admin',
            'phone' => '+919999999999',
            'password' => Hash::make('Admin@123'),
            'role_id' => $platformRole?->id,
            'saloon_id' => null,
            'onboarding_completed_at' => now(),
            'email_verified_at' => now(),
            'is_active' => true,
        ]);

        $user->forceFill([
            'is_system_admin' => true,
        ])->save();
    }
}
