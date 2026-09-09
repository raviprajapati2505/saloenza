<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('salon_settings', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('saloon_id')->constrained('saloons')->cascadeOnDelete();
            $table->string('group', 64);
            $table->string('key', 128);
            $table->json('value')->nullable();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['saloon_id', 'group', 'key']);
            $table->index(['saloon_id', 'group']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('salon_settings');
    }
};
