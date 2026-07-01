<?php
// إنشاء جدول أجهزة الأندرويد المسجلة كبوابات رسائل.

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
        Schema::create('sms_gateway_devices', function (Blueprint $table) {
            $table->id();
            
            // ربط المستأجر (Multi-Tenant Isolation) والمدقق
            $table->foreignId('company_id')->constrained('companies')->onDelete('cascade');
            $table->foreignId('created_by')->nullable()->constrained('users')->onDelete('set null');
            
            // معرفات الجهاز الفريدة
            $table->string('android_id')->index();
            $table->string('uuid')->unique();
            
            // تفاصيل العتاد والتشغيل
            $table->string('device_name');
            $table->string('brand');
            $table->string('model');
            $table->string('android_version');
            $table->string('app_version');
            
            // مصفوفة إمكانيات الجهاز (capabilities) المخزنة كـ JSON
            $table->json('capabilities')->nullable();
            
            // حالة الاتصال والتشغيل
            $table->string('status')->default('active')->index();
            $table->timestamp('last_seen_at')->nullable()->index();
            
            $table->timestamps();
            $table->softDeletes();
            
            // فهارس إضافية للأداء
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sms_gateway_devices');
    }
};
