<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gift_cards', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('saloon_id')->constrained('saloons')->cascadeOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained('saloon_branches')->nullOnDelete();
            $table->string('code', 40);
            $table->decimal('initial_balance', 12, 2);
            $table->decimal('current_balance', 12, 2);
            $table->string('currency', 10)->default('INR');
            $table->string('status', 20)->default('active');
            $table->foreignId('purchaser_customer_id')->nullable()->constrained('customers')->nullOnDelete();
            $table->string('recipient_name', 160)->nullable();
            $table->string('recipient_phone', 40)->nullable();
            $table->timestamp('issued_at');
            $table->timestamp('expires_at')->nullable();
            $table->foreignId('sold_appointment_id')->nullable()->constrained('appointments')->nullOnDelete();
            $table->foreignId('issued_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['saloon_id', 'code']);
            $table->index(['saloon_id', 'status']);
            $table->index(['saloon_id', 'purchaser_customer_id']);
        });

        Schema::create('gift_card_transactions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('gift_card_id')->constrained('gift_cards')->cascadeOnDelete();
            $table->foreignId('saloon_id')->constrained('saloons')->cascadeOnDelete();
            $table->string('type', 20);
            $table->decimal('amount', 12, 2);
            $table->decimal('balance_after', 12, 2);
            $table->foreignId('appointment_id')->nullable()->constrained('appointments')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('reason', 500)->nullable();
            $table->timestamps();

            $table->index(['gift_card_id', 'type']);
            $table->index(['saloon_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gift_card_transactions');
        Schema::dropIfExists('gift_cards');
    }
};
