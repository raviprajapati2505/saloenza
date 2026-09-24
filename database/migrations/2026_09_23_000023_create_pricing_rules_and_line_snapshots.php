<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pricing_rules', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('saloon_id')->constrained('saloons')->cascadeOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained('saloon_branches')->nullOnDelete();
            $table->string('name', 160);
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('priority')->default(0);
            $table->string('adjustment_type', 30);
            $table->decimal('adjustment_value', 12, 2)->default(0);
            $table->json('service_ids')->nullable();
            $table->json('category_ids')->nullable();
            $table->json('staff_ids')->nullable();
            $table->json('days_of_week')->nullable();
            $table->time('time_start')->nullable();
            $table->time('time_end')->nullable();
            $table->date('date_from')->nullable();
            $table->date('date_to')->nullable();
            $table->unsignedSmallInteger('min_lead_hours')->nullable();
            $table->string('channel', 30)->default('all');
            $table->boolean('stackable')->default(false);
            $table->timestamps();

            $table->index(['saloon_id', 'is_active', 'priority']);
            $table->index(['saloon_id', 'branch_id']);
        });

        Schema::table('appointment_services', function (Blueprint $table): void {
            if (! Schema::hasColumn('appointment_services', 'list_price')) {
                $table->decimal('list_price', 12, 2)->nullable()->after('price');
            }
            if (! Schema::hasColumn('appointment_services', 'applied_pricing_rule_id')) {
                $table->foreignId('applied_pricing_rule_id')
                    ->nullable()
                    ->after('list_price')
                    ->constrained('pricing_rules')
                    ->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('appointment_services', function (Blueprint $table): void {
            if (Schema::hasColumn('appointment_services', 'applied_pricing_rule_id')) {
                $table->dropConstrainedForeignId('applied_pricing_rule_id');
            }
            if (Schema::hasColumn('appointment_services', 'list_price')) {
                $table->dropColumn('list_price');
            }
        });

        Schema::dropIfExists('pricing_rules');
    }
};
