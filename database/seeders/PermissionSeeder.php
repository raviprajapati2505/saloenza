<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * Backward-compatible alias for the application's destructive permission reset.
 */
class PermissionSeeder extends Seeder
{
    public static function definitions(): array
    {
        return ApplicationPermissionSeeder::definitions();
    }

    public static function allPermissionCodes(): array
    {
        return ApplicationPermissionSeeder::allPermissionCodes();
    }

    public static function platformPermissionCodes(): array
    {
        return array_values(array_filter(
            self::allPermissionCodes(),
            fn (string $code): bool => str_starts_with($code, 'platform.'),
        ));
    }

    public static function ownerPermissionCodes(): array
    {
        return ApplicationPermissionSeeder::tenantPermissionCodes();
    }

    public static function franchiseManagerPermissionCodes(): array
    {
        return ApplicationPermissionSeeder::managerPermissionCodes();
    }

    public static function branchManagerPermissionCodes(): array
    {
        return ApplicationPermissionSeeder::branchManagerPermissionCodes();
    }

    public static function staffPermissionCodes(): array
    {
        return ApplicationPermissionSeeder::staffPermissionCodes();
    }

    public function run(): void
    {
        $this->call(ApplicationPermissionSeeder::class);
    }
}
