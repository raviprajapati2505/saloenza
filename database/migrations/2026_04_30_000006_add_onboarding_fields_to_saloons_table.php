<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('saloons', function (Blueprint $table): void {
            $table->string('branch_name')->nullable()->after('name');
            $table->text('address')->nullable()->after('branch_name');
            $table->string('city', 120)->nullable()->after('address');
            $table->string('state', 120)->nullable()->after('city');
            $table->string('phone', 20)->nullable()->after('state');
            $table->string('whatsapp', 20)->nullable()->after('phone');
            $table->string('gst_number', 20)->nullable()->after('whatsapp');
            $table->json('working_hours')->nullable()->after('gst_number');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('saloons', function (Blueprint $table): void {
            $table->dropColumn([
                'branch_name',
                'address',
                'city',
                'state',
                'phone',
                'whatsapp',
                'gst_number',
                'working_hours',
            ]);
        });
    }
};
