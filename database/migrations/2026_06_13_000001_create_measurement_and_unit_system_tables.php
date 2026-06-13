<?php
// تعليق عربي: ميجريشن لتهيئة نظام قياس متكامل يشمل المجموعات، الوحدات، التحويلات، الأسعار وتحديث جداول المنتجات والفواتير
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. جدول مجموعات الوحدات
        Schema::create('unit_groups', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('type'); // weight, length, volume, area, count, custom
            $table->foreignId('company_id')->nullable()->constrained('companies')->onDelete('cascade');
            $table->foreignId('created_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamps();
        });

        // 2. جدول الوحدات
        Schema::create('units', function (Blueprint $table) {
            $table->id();
            $table->foreignId('unit_group_id')->constrained('unit_groups')->onDelete('cascade');
            $table->string('name');
            $table->string('code');
            $table->integer('decimal_places')->default(0);
            $table->boolean('is_active')->default(true);
            $table->foreignId('company_id')->nullable()->constrained('companies')->onDelete('cascade');
            $table->foreignId('created_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamps();
        });

        // 3. جدول تحويلات الوحدات
        Schema::create('unit_conversions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('unit_group_id')->constrained('unit_groups')->onDelete('cascade');
            $table->foreignId('from_unit_id')->constrained('units')->onDelete('cascade');
            $table->foreignId('to_unit_id')->constrained('units')->onDelete('cascade');
            $table->decimal('factor', 18, 6);
            $table->decimal('reverse_factor', 18, 6);
            $table->foreignId('company_id')->nullable()->constrained('companies')->onDelete('cascade');
            $table->foreignId('created_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamps();
        });

        // 4. جدول ربط الوحدات بمتغيرات المنتجات
        Schema::create('product_variant_units', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_variant_id')->constrained('product_variants')->onDelete('cascade');
            $table->foreignId('unit_id')->constrained('units')->onDelete('cascade');
            $table->decimal('conversion_factor_to_base', 18, 6);
            $table->boolean('is_default')->default(false);
            $table->decimal('min_quantity', 18, 4)->nullable();
            $table->decimal('max_quantity', 18, 4)->nullable();
            $table->boolean('allow_fraction')->default(false);
            $table->timestamps();
        });

        // 5. جدول أسعار الوحدات لمتغيرات المنتجات
        Schema::create('product_variant_unit_prices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_variant_id')->constrained('product_variants')->onDelete('cascade');
            $table->foreignId('unit_id')->constrained('units')->onDelete('cascade');
            $table->decimal('price', 18, 2);
            $table->decimal('cost', 18, 2)->nullable();
            $table->date('effective_from')->nullable();
            $table->date('effective_to')->nullable();
            $table->boolean('is_default')->default(true);
            $table->timestamps();
        });

        // 6. تعديل جدول المنتجات لإضافة حقول ضبط الوحدات
        Schema::table('products', function (Blueprint $table) {
            $table->foreignId('base_unit_id')->nullable()->constrained('units')->onDelete('set null');
            $table->foreignId('purchase_unit_id')->nullable()->constrained('units')->onDelete('set null');
            $table->foreignId('display_unit_id')->nullable()->constrained('units')->onDelete('set null');
            $table->boolean('allow_decimal_quantities')->default(false);
            $table->integer('quantity_precision')->default(2);
        });

        // 7. تعديل جدول متغيرات المنتجات لإضافة حقول ضبط الوحدات
        Schema::table('product_variants', function (Blueprint $table) {
            $table->foreignId('base_unit_id')->nullable()->constrained('units')->onDelete('set null');
            $table->foreignId('purchase_unit_id')->nullable()->constrained('units')->onDelete('set null');
            $table->foreignId('display_unit_id')->nullable()->constrained('units')->onDelete('set null');
        });

        // 8. تعديل جدول بنود الفواتير لحفظ لقطات المعاملة المالية والوحدات
        Schema::table('invoice_items', function (Blueprint $table) {
            $table->foreignId('unit_id')->nullable()->constrained('units')->onDelete('set null');
            $table->decimal('conversion_factor_snapshot', 18, 6)->nullable();
            $table->decimal('base_quantity', 18, 6)->nullable();
            $table->decimal('unit_price_snapshot', 18, 2)->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('invoice_items', function (Blueprint $table) {
            $table->dropForeign(['unit_id']);
            $table->dropColumn(['unit_id', 'conversion_factor_snapshot', 'base_quantity', 'unit_price_snapshot']);
        });

        Schema::table('product_variants', function (Blueprint $table) {
            $table->dropForeign(['base_unit_id']);
            $table->dropForeign(['purchase_unit_id']);
            $table->dropForeign(['display_unit_id']);
            $table->dropColumn(['base_unit_id', 'purchase_unit_id', 'display_unit_id']);
        });

        Schema::table('products', function (Blueprint $table) {
            $table->dropForeign(['base_unit_id']);
            $table->dropForeign(['purchase_unit_id']);
            $table->dropForeign(['display_unit_id']);
            $table->dropColumn(['base_unit_id', 'purchase_unit_id', 'display_unit_id', 'allow_decimal_quantities', 'quantity_precision']);
        });

        Schema::dropIfExists('product_variant_unit_prices');
        Schema::dropIfExists('product_variant_units');
        Schema::dropIfExists('unit_conversions');
        Schema::dropIfExists('units');
        Schema::dropIfExists('unit_groups');
    }
};
