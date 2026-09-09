<?php

use Illuminate\Database\Migrations\Migration;

/**
 * Platform super admin permissions are synced by Database\Seeders\RoleSeeder.
 */
return new class extends Migration
{
    public function up(): void
    {
        // no-op — run: php artisan db:seed --class=RoleSeeder
    }

    public function down(): void
    {
        //
    }
};
