<?php
// إنشاء جدول شرائح الاتصال المكتشفة والنشطة على الأجهزة.

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
        Schema::dropIfExists('sms_gateway_lines');
        Schema::create('sms_gateway_lines', function (Blueprint $table) {
            $table->id();
            
            // روابط الشركة، الجهاز والمستخدم
            $table->foreignId('sms_device_id')->constrained('sms_gateway_devices')->onDelete('cascade');
            $table->foreignId('company_id')->constrained('companies')->onDelete('cascade');
            $table->foreignId('created_by')->nullable()->constrained('users')->onDelete('set null');
            
            // تفاصيل مكان وتعرف الشريحة
            $table->integer('slot_index'); // 0 or 1
            $table->string('subscription_id')->index(); // معرف الاشتراك بالأندرويد
            
            $table->string('carrier'); // اتصالات، فودافون، إلخ
            $table->string('mcc')->nullable();
            $table->string('mnc')->nullable();
            $table->string('phone_number')->nullable()->index();
            $table->string('network_type')->nullable();
            $table->integer('signal_strength')->nullable();
            
            // حالة تشغيل الخط
            $table->string('status')->default('active')->index(); // active, disabled, no_signal
            
            $table->timestamps();
            
            // الفهارس
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sms_gateway_lines');
    }
};
