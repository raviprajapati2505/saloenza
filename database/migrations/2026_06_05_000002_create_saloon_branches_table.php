<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('saloon_branches', function (Blueprint $table): void {
            $table->id();
            $table->string('branch_name', 120);
            $table->string('business_address_1');
            $table->string('business_address_2')->nullable();
            $table->foreignId('saloon_id')->constrained('saloons')->cascadeOnDelete();
            $table->string('city', 120);
            $table->string('state', 120);
            $table->string('area_pincode', 20);
            $table->string('country', 120)->default('India');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('saloon_branches');
    }
};
