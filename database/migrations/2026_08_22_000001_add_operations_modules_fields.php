<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('appointments', function (Blueprint $table): void {
            if (! Schema::hasColumn('appointments', 'booking_source')) {
                $table->string('booking_source', 30)->nullable()->after('type')->index();
            }
        });

        Schema::create('salon_booking_links', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('saloon_id')->constrained('saloons')->cascadeOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained('saloon_branches')->nullOnDelete();
            $table->string('token', 64)->unique();
            $table->string('label')->nullable();
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['saloon_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('salon_booking_links');

        Schema::table('appointments', function (Blueprint $table): void {
            if (Schema::hasColumn('appointments', 'booking_source')) {
                $table->dropColumn('booking_source');
            }
        });
    }
};
