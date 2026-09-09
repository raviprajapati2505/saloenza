<?php

use Illuminate\Database\Migrations\Migration;

/**
 * Demo seed moved to Database\Seeders\DemoDataSeeder so schema migrations run first.
 */
return new class extends Migration
{
    public function up(): void
    {
        // no-op — run: php artisan db:seed --class=DemoDataSeeder
    }

    public function down(): void
    {
        //
    }
};
