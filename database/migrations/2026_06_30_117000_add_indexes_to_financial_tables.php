<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * إضافة فهارس إلى الجداول المالية لضمان الأداء السريع ومنع بطء الاستعلامات عند زيادة حجم البيانات.
 */
class AddIndexesToFinancialTables extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('stakeholder_financial_balances', function (Blueprint $table) {
            // فهرس مركب للاستعلام السريع عن أرصدة الأطراف ضمن مستأجر معين
            $table->index(['company_id', 'user_id', 'relation_type'], 'idx_stakeholder_balances_comp_user_rel');
        });

        Schema::table('transactions', function (Blueprint $table) {
            // فهارس للربط والتصفية حسب الفواتير والخزائن والأطراف
            $table->index('source_invoice_id', 'idx_transactions_source_invoice');
            $table->index('cashbox_id', 'idx_transactions_cashbox');
            $table->index('user_id', 'idx_transactions_user');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('stakeholder_financial_balances', function (Blueprint $table) {
            $table->dropIndex('idx_stakeholder_balances_comp_user_rel');
        });

        Schema::table('transactions', function (Blueprint $table) {
            $table->dropIndex('idx_transactions_source_invoice');
            $table->dropIndex('idx_transactions_cashbox');
            $table->dropIndex('idx_transactions_user');
        });
    }
}
