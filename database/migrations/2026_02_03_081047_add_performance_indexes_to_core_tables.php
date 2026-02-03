<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. تحسين البحث والفلترة في المنتجات
        Schema::table('products', function (Blueprint $table) {
            $table->index(['company_id', 'active', 'category_id'], 'idx_products_performance');
        });

        // 2. تحسين لوحة التحكم والبحث في الفواتير
        Schema::table('invoices', function (Blueprint $table) {
            $table->index(['company_id', 'invoice_type_id', 'status', 'created_at'], 'idx_invoices_performance');
            $table->index(['user_id', 'status'], 'idx_invoices_user_status');
        });

        // 3. تحسين العمليات المشتركة والتقارير في تفاصيل الفواتير
        Schema::table('invoice_items', function (Blueprint $table) {
            $table->index(['company_id', 'invoice_id', 'product_id'], 'idx_items_performance');
        });

        // 4. تحسين تقارير المدفوعات
        Schema::table('payments', function (Blueprint $table) {
            $table->index(['company_id', 'payment_date', 'method'], 'idx_payments_performance');
        });

        // 5. تحسين الخزن الافتراضية
        Schema::table('cash_boxes', function (Blueprint $table) {
            $table->index(['company_id', 'user_id', 'is_default'], 'idx_cash_boxes_performance');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('products', fn(Blueprint $table) => $table->dropIndex('idx_products_performance'));
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropIndex('idx_invoices_performance');
            $table->dropIndex('idx_invoices_user_status');
        });
        Schema::table('invoice_items', fn(Blueprint $table) => $table->dropIndex('idx_items_performance'));
        Schema::table('payments', fn(Blueprint $table) => $table->dropIndex('idx_payments_performance'));
        Schema::table('cash_boxes', fn(Blueprint $table) => $table->dropIndex('idx_cash_boxes_performance'));
    }
};
