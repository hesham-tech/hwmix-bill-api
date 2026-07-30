<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('hwnix_cash_financial_accounts', function (Blueprint $table) {
            $table->string('daily_withdraw_alert_type', 20)->default('percentage')->after('daily_withdraw_limit');
            $table->decimal('daily_withdraw_alert_value', 18, 2)->default(80.00)->after('daily_withdraw_alert_type');

            $table->string('daily_deposit_alert_type', 20)->default('percentage')->after('daily_deposit_limit');
            $table->decimal('daily_deposit_alert_value', 18, 2)->default(80.00)->after('daily_deposit_alert_type');

            $table->string('monthly_withdraw_alert_type', 20)->default('percentage')->after('monthly_withdraw_limit');
            $table->decimal('monthly_withdraw_alert_value', 18, 2)->default(80.00)->after('monthly_withdraw_alert_type');

            $table->string('monthly_deposit_alert_type', 20)->default('percentage')->after('monthly_deposit_limit');
            $table->decimal('monthly_deposit_alert_value', 18, 2)->default(80.00)->after('monthly_deposit_alert_type');
        });
    }

    public function down(): void
    {
        Schema::table('hwnix_cash_financial_accounts', function (Blueprint $table) {
            $table->dropColumn([
                'daily_withdraw_alert_type',
                'daily_withdraw_alert_value',
                'daily_deposit_alert_type',
                'daily_deposit_alert_value',
                'monthly_withdraw_alert_type',
                'monthly_withdraw_alert_value',
                'monthly_deposit_alert_type',
                'monthly_deposit_alert_value',
            ]);
        });
    }
};
