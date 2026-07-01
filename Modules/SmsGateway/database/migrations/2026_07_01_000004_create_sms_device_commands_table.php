<?php
// إنشاء جدول أوامر الأجهزة الموجهة لتطبيق الأندرويد.

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
        Schema::dropIfExists('sms_gateway_device_commands');
        Schema::create('sms_gateway_device_commands', function (Blueprint $table) {
            $table->id();
            
            // ربط الأمر بالجهاز المطلوب تنفيذه عليه
            $table->foreignId('sms_device_id')->constrained('sms_gateway_devices')->onDelete('cascade');
            
            // نوع وحزمة الأمر
            $table->string('command_type')->index(); // SEND_SMS, REFRESH_DEVICE, etc.
            $table->json('payload')->nullable();     // البارامترات المطلوبة
            
            // حالة تنفيذ وحالة التدقيق
            $table->string('status')->default('pending')->index(); // pending, sending, executed, failed, cancelled
            $table->json('response_payload')->nullable(); // الرد ومخرجات التنفيذ
            
            // منع التكرار وإعادة التنفيذ
            $table->string('idempotency_key')->nullable()->unique();
            
            $table->timestamp('executed_at')->nullable()->index();
            $table->timestamps();
            
            // فهارس إضافية للفلترة والأداء
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sms_gateway_device_commands');
    }
};
