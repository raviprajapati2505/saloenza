<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('appointment_services', function (Blueprint $table): void {
            if (! Schema::hasColumn('appointment_services', 'is_staff_locked')) {
                $table->boolean('is_staff_locked')->default(false)->after('staff_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('appointment_services', function (Blueprint $table): void {
            if (Schema::hasColumn('appointment_services', 'is_staff_locked')) {
                $table->dropColumn('is_staff_locked');
            }
        });
    }
};
