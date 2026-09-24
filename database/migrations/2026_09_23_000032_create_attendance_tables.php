<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shifts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('saloon_id')->constrained('saloons')->cascadeOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained('saloon_branches')->nullOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->dateTime('starts_at');
            $table->dateTime('ends_at');
            $table->string('source', 32)->default('manual'); // generated_from_weekly|manual
            $table->string('status', 24)->default('scheduled'); // scheduled|completed|cancelled
            $table->unsignedSmallInteger('break_minutes')->default(0);
            $table->timestamps();

            $table->index(['saloon_id', 'user_id', 'starts_at']);
            $table->index(['user_id', 'starts_at']);
        });

        Schema::create('attendance_punches', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('saloon_id')->constrained('saloons')->cascadeOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained('saloon_branches')->nullOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('type', 16); // in|out
            $table->dateTime('punched_at');
            $table->string('method', 24)->default('app'); // app|kiosk|manager_override
            $table->decimal('geo_lat', 10, 7)->nullable();
            $table->decimal('geo_lng', 10, 7)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['saloon_id', 'user_id', 'punched_at']);
            $table->index(['user_id', 'punched_at']);
        });

        Schema::create('leave_requests', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('saloon_id')->constrained('saloons')->cascadeOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained('saloon_branches')->nullOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('type', 24)->default('annual'); // annual|sick|unpaid|other
            $table->string('status', 24)->default('pending'); // pending|approved|rejected
            $table->date('from_date');
            $table->date('to_date');
            $table->text('reason')->nullable();
            $table->foreignId('approver_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('decided_at')->nullable();
            $table->text('decision_notes')->nullable();
            $table->timestamps();

            $table->index(['saloon_id', 'status']);
            $table->index(['user_id', 'from_date', 'to_date']);
            $table->index(['status', 'from_date', 'to_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('leave_requests');
        Schema::dropIfExists('attendance_punches');
        Schema::dropIfExists('shifts');
    }
};
