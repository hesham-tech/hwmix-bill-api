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
        // 1. تحديث فواتير المشتريات: التكلفة هي نفس سعر الوحدة المدفوع
        DB::statement("
            UPDATE invoice_items 
            JOIN invoices ON invoice_items.invoice_id = invoices.id
            SET invoice_items.cost_price = invoice_items.unit_price,
                invoice_items.total_cost = invoice_items.unit_price * invoice_items.quantity
            WHERE invoices.invoice_type_code IN ('purchase', 'return_purchase')
            AND invoice_items.cost_price IS NULL
        ");

        // 2. تحديث فواتير المبيعات: نجلب سعر التكلفة من المنتج (بيانات تاريخية تقريبية)
        DB::statement("
            UPDATE invoice_items 
            JOIN invoices ON invoice_items.invoice_id = invoices.id
            JOIN product_variants ON invoice_items.variant_id = product_variants.id
            SET invoice_items.cost_price = product_variants.purchase_price,
                invoice_items.total_cost = product_variants.purchase_price * invoice_items.quantity
            WHERE invoices.invoice_type_code IN ('sale', 'return_sale')
            AND invoice_items.cost_price IS NULL
        ");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('invoice_items')->update([
            'cost_price' => null,
            'total_cost' => null,
        ]);
    }
};
