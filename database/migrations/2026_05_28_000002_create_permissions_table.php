<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('permissions', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('name', 120);
            $table->string('code', 120)->unique();
            $table->string('module', 80);
            $table->string('scope', 20)->default('tenant');
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('permissions');
    }
};
