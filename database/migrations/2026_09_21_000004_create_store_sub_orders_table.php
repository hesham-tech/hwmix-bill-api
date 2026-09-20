<?php
// جدول الطلبات الفرعية — كل Sub-Order يخص شركة واحدة من شركات المتجر
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('store_sub_orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_order_id')->constrained('store_orders')->cascadeOnDelete();
            $table->foreignId('company_id')->constrained('companies');
            $table->foreignId('invoice_id')->nullable()->constrained('invoices')->nullOnDelete();
            $table->string('sub_order_number', 40)->unique();
            $table->enum('status', [
                'pending', 'confirmed', 'processing',
                'shipped', 'delivered', 'cancelled'
            ])->default('pending');
            $table->string('tracking_number')->nullable();
            $table->string('shipping_carrier')->nullable();
            $table->decimal('subtotal', 18, 2)->default(0);
            $table->decimal('shipping_cost', 18, 2)->default(0);
            $table->text('vendor_notes')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index('company_id');
            $table->index('store_order_id');
        });
    }
    public function down(): void
    {
        Schema::dropIfExists('store_sub_orders');
    }
};
