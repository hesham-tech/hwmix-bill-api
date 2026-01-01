<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            // نوع المنتج
            $table->enum('product_type', [
                'physical',      // منتج حقيقي (افتراضي)
                'digital',       // منتج رقمي (كروسات، ألعاب، بطاقات شحن)
                'service',       // خدمة
                'subscription',  // اشتراك
            ])->default('physical')->after('name');

            // هل يحتاج مخزون؟
            $table->boolean('require_stock')->default(true)->after('product_type');

            // معلومات المنتج الرقمي القابل للتنزيل
            $table->boolean('is_downloadable')->default(false)->after('require_stock');
            $table->text('download_url')->nullable()->after('is_downloadable');
            $table->integer('download_limit')->nullable()->after('download_url'); // عدد مرات التنزيل المسموحة

            // مفاتيح التفعيل/الكروسات (JSON array)
            $table->json('license_keys')->nullable()->after('download_limit');
            $table->integer('available_keys_count')->default(0)->after('license_keys');

            // صلاحية المنتج الرقمي
            $table->integer('validity_days')->nullable()->after('available_keys_count'); // مدة الصلاحية بالأيام
            $table->datetime('expires_at')->nullable()->after('validity_days'); // تاريخ انتهاء الصلاحية

            // ملاحظات للمنتج الرقمي
            $table->text('delivery_instructions')->nullable()->after('expires_at'); // تعليمات التسليم
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn([
                'product_type',
                'require_stock',
                'is_downloadable',
                'download_url',
                'download_limit',
                'license_keys',
                'available_keys_count',
                'validity_days',
                'expires_at',
                'delivery_instructions',
            ]);
        });
    }
};
