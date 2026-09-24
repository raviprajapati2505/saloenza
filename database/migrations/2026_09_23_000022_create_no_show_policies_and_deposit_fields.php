<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('no_show_policies', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('saloon_id')->constrained('saloons')->cascadeOnDelete();
            $table->boolean('is_enabled')->default(false);
            $table->string('apply_mode', 40)->default('repeat_offenders');
            $table->unsignedSmallInteger('min_no_shows')->default(2);
            $table->unsignedSmallInteger('window_days')->default(90);
            $table->string('protection_type', 40)->default('deposit');
            $table->string('deposit_type', 20)->default('fixed');
            $table->decimal('deposit_value', 12, 2)->default(0);
            $table->string('fee_type', 20)->default('percent');
            $table->decimal('fee_value', 12, 2)->default(100);
            $table->unsignedSmallInteger('cancel_cutoff_hours')->default(24);
            $table->string('currency', 10)->nullable();
            $table->timestamps();

            $table->unique('saloon_id');
        });

        Schema::table('appointments', function (Blueprint $table): void {
            if (! Schema::hasColumn('appointments', 'deposit_required_amount')) {
                $table->decimal('deposit_required_amount', 12, 2)->nullable()->after('amount_paid');
            }
            if (! Schema::hasColumn('appointments', 'deposit_status')) {
                $table->string('deposit_status', 30)->default('not_required')->after('deposit_required_amount');
            }
            if (! Schema::hasColumn('appointments', 'deposit_paid_at')) {
                $table->timestamp('deposit_paid_at')->nullable()->after('deposit_status');
            }
            if (! Schema::hasColumn('appointments', 'no_show_fee_amount')) {
                $table->decimal('no_show_fee_amount', 12, 2)->nullable()->after('deposit_paid_at');
            }
            if (! Schema::hasColumn('appointments', 'no_show_fee_status')) {
                $table->string('no_show_fee_status', 30)->default('n/a')->after('no_show_fee_amount');
            }
        });

        Schema::table('customers', function (Blueprint $table): void {
            if (! Schema::hasColumn('customers', 'require_prepayment')) {
                $table->boolean('require_prepayment')->default(false)->after('is_active');
            }
        });
    }

    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table): void {
            if (Schema::hasColumn('customers', 'require_prepayment')) {
                $table->dropColumn('require_prepayment');
            }
        });

        Schema::table('appointments', function (Blueprint $table): void {
            $columns = array_filter([
                Schema::hasColumn('appointments', 'deposit_required_amount') ? 'deposit_required_amount' : null,
                Schema::hasColumn('appointments', 'deposit_status') ? 'deposit_status' : null,
                Schema::hasColumn('appointments', 'deposit_paid_at') ? 'deposit_paid_at' : null,
                Schema::hasColumn('appointments', 'no_show_fee_amount') ? 'no_show_fee_amount' : null,
                Schema::hasColumn('appointments', 'no_show_fee_status') ? 'no_show_fee_status' : null,
            ]);

            if ($columns !== []) {
                $table->dropColumn($columns);
            }
        });

        Schema::dropIfExists('no_show_policies');
    }
};
