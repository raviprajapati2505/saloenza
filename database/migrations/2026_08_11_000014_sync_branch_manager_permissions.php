<?php

use Illuminate\Database\Migrations\Migration;

/**
 * System role permission sync moved to Database\Seeders\RoleSeeder.
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
