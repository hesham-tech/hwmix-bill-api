<?php
// ترحيل لطلب إضافة حقول حدود المحافظ الإلكترونية لجدول خطوط كاش هونكس.

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('hwnix_cash_lines', function (Blueprint $table) {
            $table->decimal('daily_withdraw_limit', 18, 2)->nullable()->after('daily_limit');
            $table->decimal('daily_deposit_limit', 18, 2)->nullable()->after('daily_withdraw_limit');
            $table->decimal('monthly_withdraw_limit', 18, 2)->nullable()->after('daily_deposit_limit');
            $table->decimal('monthly_deposit_limit', 18, 2)->nullable()->after('monthly_withdraw_limit');
        });
    }

    public function down(): void
    {
        Schema::table('hwnix_cash_lines', function (Blueprint $table) {
            $table->dropColumn([
                'daily_withdraw_limit',
                'daily_deposit_limit',
                'monthly_withdraw_limit',
                'monthly_deposit_limit',
            ]);
        });
    }
};
