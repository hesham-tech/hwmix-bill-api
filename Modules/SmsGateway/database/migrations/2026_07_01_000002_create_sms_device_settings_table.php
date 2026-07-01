<?php
// إنشاء جدول إعدادات أجهزة بوابة الرسائل وإصدارات التكوين.

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
        Schema::disableForeignKeyConstraints();
        Schema::dropIfExists('smsgate_device_settings');
        Schema::create('smsgate_device_settings', function (Blueprint $table) {
            $table->id();
            
            // الربط بالجهاز (علاقة رأس برأس One-to-One)
            $table->foreignId('sms_device_id')->constrained('smsgate_devices')->onDelete('cascade');
            
            // رقم إصدار التكوين (تحديثه يعني سحب الإعدادات مجدداً بالهاتف)
            $table->integer('configuration_version')->default(1);
            
            // إعدادات التشغيل
            $table->integer('polling_interval_seconds')->default(60);
            $table->integer('max_retry_count')->default(3);
            $table->string('logging_level')->default('info'); // debug, info, warn, error
            
            // الحقول المرنة والـ Flags
            $table->json('feature_flags')->nullable(); // ميزات تشغيلية
            $table->json('sync_limits')->nullable();  // حدود المزامنة كالإرسال اليومي والأقصى
            
            $table->timestamps();
        });
        Schema::enableForeignKeyConstraints();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::disableForeignKeyConstraints();
        Schema::dropIfExists('smsgate_device_settings');
        Schema::enableForeignKeyConstraints();
    }
};
