<?php
// ترحيل لإضافة حقلي تتبع مصدر ومرحلة التحليل لجدول معاملات المحافظ.

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('hwnix_cash_wallet_transactions', function (Blueprint $table) {
            $table->string('parsed_by')->nullable()->after('source')->index();
            $table->string('parser_stage')->nullable()->after('parsed_by')->index();
        });
    }

    public function down(): void
    {
        Schema::table('hwnix_cash_wallet_transactions', function (Blueprint $table) {
            $table->dropColumn(['parsed_by', 'parser_stage']);
        });
    }
};
