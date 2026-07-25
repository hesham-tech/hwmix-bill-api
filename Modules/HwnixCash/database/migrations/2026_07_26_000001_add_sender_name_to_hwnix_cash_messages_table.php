<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('hwnix_cash_messages') && !Schema::hasColumn('hwnix_cash_messages', 'sender_name')) {
            Schema::table('hwnix_cash_messages', function (Blueprint $table) {
                $table->string('sender_name')->nullable()->after('phone_number');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('hwnix_cash_messages') && Schema::hasColumn('hwnix_cash_messages', 'sender_name')) {
            Schema::table('hwnix_cash_messages', function (Blueprint $table) {
                $table->dropColumn('sender_name');
            });
        }
    }
};
