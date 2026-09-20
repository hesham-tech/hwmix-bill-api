<?php
// جدول الطلبات الرئيسية للمتجر الإلكتروني — يجمع كل عناصر الطلب من شركات مختلفة
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('store_orders', function (Blueprint $table) {
            $table->id();
            $table->string('order_number', 30)->unique();
            $table->foreignId('customer_user_id')->constrained('users');
            $table->foreignId('shipping_address_id')->constrained('customer_addresses');
            $table->enum('status', [
                'pending', 'confirmed', 'processing',
                'partially_shipped', 'shipped', 'delivered',
                'cancelled', 'returned'
            ])->default('pending');
            $table->enum('payment_method', ['cod', 'online'])->default('cod');
            $table->enum('payment_status', ['unpaid', 'paid', 'partial', 'refunded'])->default('unpaid');
            $table->decimal('subtotal', 18, 2)->default(0);
            $table->decimal('shipping_total', 18, 2)->default(0);
            $table->decimal('discount_total', 18, 2)->default(0);
            $table->decimal('total_amount', 18, 2)->default(0);
            $table->text('customer_notes')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index('customer_user_id');
            $table->index('status');
        });
    }
    public function down(): void
    {
        Schema::dropIfExists('store_orders');
    }
};
