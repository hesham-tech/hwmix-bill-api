<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // دالة مساعدة للتأكد من وجود الفهرس قبل إضافته
        $addIndexIfNotExists = function (string $tableName, array $columns, string $indexName) {
            $indices = DB::select("SHOW INDEX FROM " . $tableName . " WHERE Key_name = '" . $indexName . "'");
            if (empty($indices)) {
                Schema::table($tableName, function (Blueprint $table) use ($columns, $indexName) {
                    $table->index($columns, $indexName);
                });
            }
        };

        // 1. تحسين البحث والفلترة في المنتجات
        $addIndexIfNotExists('products', ['company_id', 'active', 'category_id'], 'idx_products_performance');

        // 2. تحسين لوحة التحكم والبحث في الفواتير
        $addIndexIfNotExists('invoices', ['company_id', 'invoice_type_id', 'status', 'created_at'], 'idx_invoices_performance');
        $addIndexIfNotExists('invoices', ['user_id', 'status'], 'idx_invoices_user_status');

        // 3. تحسين العمليات المشتركة والتقارير في تفاصيل الفواتير
        $addIndexIfNotExists('invoice_items', ['company_id', 'invoice_id', 'product_id'], 'idx_items_performance');

        // 4. تحسين تقارير المدفوعات
        $addIndexIfNotExists('payments', ['company_id', 'payment_date', 'method'], 'idx_payments_performance');

        // 5. تحسين الخزن الافتراضية
        $addIndexIfNotExists('cash_boxes', ['company_id', 'user_id', 'is_default'], 'idx_cash_boxes_performance');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // No need for complex down in dry run simulation
    }
};
