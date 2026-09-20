<?php
// جدول عناصر الطلب — كل سطر يمثل منتجاً واحداً مع snapshot لبيانات المنتج وقت الطلب
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('store_order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_order_id')->constrained('store_orders')->cascadeOnDelete();
            $table->foreignId('store_sub_order_id')->constrained('store_sub_orders')->cascadeOnDelete();
            $table->foreignId('product_variant_id')->constrained('product_variants');
            $table->foreignId('company_id')->constrained('companies');
            $table->string('product_name_snapshot');
            $table->string('variant_sku_snapshot')->nullable();
            $table->string('variant_image_snapshot')->nullable();
            $table->decimal('unit_price', 18, 2);
            $table->decimal('quantity', 18, 6);
            $table->decimal('total_price', 18, 2);
            $table->timestamps();
            $table->index('store_sub_order_id');
            $table->index('product_variant_id');
        });
    }
    public function down(): void
    {
        Schema::dropIfExists('store_order_items');
    }
};
