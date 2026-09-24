<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customer_segments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('saloon_id')->constrained('saloons')->cascadeOnDelete();
            $table->string('name');
            $table->string('type', 32); // static|dynamic
            $table->json('rules_json')->nullable();
            $table->unsignedInteger('estimated_size')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['saloon_id', 'is_active']);
            $table->index(['saloon_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_segments');
    }
};
