<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('services', function (Blueprint $table): void {
            $table->id();
            $table->string('name', 120);
            $table->foreignId('category_id')->nullable()->constrained('categories')->nullOnDelete();
            $table->decimal('default_price', 10, 2);
            $table->unsignedSmallInteger('duration_minutes');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('services');
    }
};
