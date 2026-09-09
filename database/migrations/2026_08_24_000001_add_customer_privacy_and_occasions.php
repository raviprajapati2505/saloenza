<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table): void {
            $table->foreignId('created_by')
                ->nullable()
                ->after('is_active')
                ->constrained('users')
                ->nullOnDelete();
            $table->date('birthday')->nullable()->after('created_by');
            $table->date('anniversary')->nullable()->after('birthday');
            $table->date('last_birthday_wish_on')->nullable()->after('anniversary');
            $table->date('last_anniversary_wish_on')->nullable()->after('last_birthday_wish_on');
        });
    }

    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('created_by');
            $table->dropColumn([
                'birthday',
                'anniversary',
                'last_birthday_wish_on',
                'last_anniversary_wish_on',
            ]);
        });
    }
};
