<?php

use Illuminate\Database\Migrations\Migration;

/**
 * Permission and legacy owner role consolidation moved to seeders:
 * ApplicationPermissionSeeder, LegacyRoleConsolidationSeeder, RoleSeeder.
 */
return new class extends Migration
{
    public function up(): void
    {
        // no-op — run: php artisan db:seed
    }

    public function down(): void
    {
        //
    }
};
