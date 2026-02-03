<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('digital_product_deliveries', function (Blueprint $table) {
            $table->id();

            // العلاقات
            $table->foreignId('invoice_item_id')->constrained('invoice_items')->onDelete('cascade');
            $table->foreignId('product_id')->constrained('products')->onDelete('cascade');
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade'); // المشتري

            // نوع التسليم
            $table->enum('delivery_type', [
                'license_key',          // مفتاح تفعيل/كروس
                'download_link',        // رابط تنزيل
                'account_credentials',  // بيانات دخول حساب
                'code',                 // كود/رمز
                'other',               // أخرى
            ]);

            // بيانات التسليم (JSON)
            $table->json('delivery_data')->nullable();

            // حالة التسليم
            $table->enum('status', [
                'pending',    // معلق (لم يتم التسليم بعد)
                'delivered',  // تم التسليم
                'activated',  // تم التفعيل/الاستخدام
                'expired',    // منتهي الصلاحية
                'revoked',    // ملغى
            ])->default('pending');

            // تواريخ مهمة
            $table->datetime('delivered_at')->nullable();
            $table->datetime('activated_at')->nullable();
            $table->datetime('expires_at')->nullable();

            // تتبع الاستخدام
            $table->integer('download_count')->default(0);
            $table->datetime('last_downloaded_at')->nullable();
            $table->integer('activation_count')->default(0);
            $table->datetime('last_activated_at')->nullable();

            // ملاحظات
            $table->text('notes')->nullable();

            // تتبع الشركة
            $table->foreignId('company_id')->constrained('companies')->onDelete('cascade');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();
            $table->softDeletes();

            // Indexes
            $table->index(['user_id', 'status']);
            $table->index(['product_id', 'status']);
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('digital_product_deliveries');
    }
};
