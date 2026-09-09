<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customer_tags', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('saloon_id')->constrained('saloons')->cascadeOnDelete();
            $table->string('name', 60);
            $table->string('color', 20)->default('slate');
            $table->timestamps();

            $table->unique(['saloon_id', 'name']);
            $table->index(['saloon_id', 'name']);
        });

        Schema::create('customer_customer_tag', function (Blueprint $table): void {
            $table->foreignId('customer_id')->constrained('customers')->cascadeOnDelete();
            $table->foreignId('customer_tag_id')->constrained('customer_tags')->cascadeOnDelete();
            $table->timestamps();

            $table->primary(['customer_id', 'customer_tag_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_customer_tag');
        Schema::dropIfExists('customer_tags');
    }
};
