<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // إضافة حقول الضرائب لجدول invoices
        Schema::table('invoices', function (Blueprint $table) {
            // إجمالي الضريبة
            $table->decimal('total_tax', 15, 2)->default(0)->after('total_discount');

            // نسبة الضريبة الافتراضية (%)
            $table->decimal('tax_rate', 5, 2)->nullable()->after('total_tax');

            // هل الضريبة مضمنة في السعر؟
            $table->boolean('tax_inclusive')->default(false)->after('tax_rate');
        });

        // إضافة حقول الضرائب لجدول invoice_items
        Schema::table('invoice_items', function (Blueprint $table) {
            // نسبة الضريبة للعنصر (%)
            $table->decimal('tax_rate', 5, 2)->default(0)->after('discount');

            // قيمة الضريبة المحسوبة
            $table->decimal('tax_amount', 15, 2)->default(0)->after('tax_rate');

            // السعر بعد الخصm وقبل الضريبة
            $table->decimal('subtotal', 15, 2)->default(0)->after('tax_amount');
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropColumn(['total_tax', 'tax_rate', 'tax_inclusive']);
        });

        Schema::table('invoice_items', function (Blueprint $table) {
            $table->dropColumn(['tax_rate', 'tax_amount', 'subtotal']);
        });
    }
};
