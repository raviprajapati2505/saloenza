<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customer_packages', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('saloon_id')->constrained('saloons')->cascadeOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained('saloon_branches')->nullOnDelete();
            $table->foreignId('customer_id')->constrained('customers')->cascadeOnDelete();
            $table->foreignId('package_id')->constrained('packages')->restrictOnDelete();
            $table->timestamp('purchased_at');
            $table->timestamp('expires_at')->nullable();
            $table->string('status', 20)->default('active');
            $table->decimal('amount_paid', 12, 2)->default(0);
            $table->string('payment_method', 40)->nullable();
            $table->string('payment_ref', 120)->nullable();
            $table->foreignId('sold_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['saloon_id', 'customer_id', 'status']);
            $table->index(['saloon_id', 'status']);
            $table->index(['package_id']);
        });

        Schema::create('customer_package_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('customer_package_id')->constrained('customer_packages')->cascadeOnDelete();
            $table->foreignId('package_item_id')->nullable()->constrained('package_items')->nullOnDelete();
            $table->foreignId('service_id')->constrained('services')->restrictOnDelete();
            $table->unsignedInteger('quantity_total');
            $table->unsignedInteger('quantity_remaining');
            $table->decimal('unit_value', 12, 2)->default(0);
            $table->timestamps();

            $table->unique(['customer_package_id', 'service_id'], 'cpi_customer_package_service_unique');
            $table->index(['service_id', 'quantity_remaining']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_package_items');
        Schema::dropIfExists('customer_packages');
    }
};
