<?php
// إنشاء جدول الرسائل الصادرة والواردة وتفاصيل حالاتها.

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
        Schema::dropIfExists('smsgate_messages');
        Schema::create('smsgate_messages', function (Blueprint $table) {
            $table->id();
            
            // روابط الشركة والمستخدم المنشئ
            $table->foreignId('company_id')->constrained('companies')->onDelete('cascade');
            $table->foreignId('created_by')->nullable()->constrained('users')->onDelete('set null');
            
            // روابط بوابة النقل (الجهاز والخط المستخدم)
            $table->foreignId('sms_device_id')->constrained('smsgate_devices')->onDelete('cascade');
            $table->foreignId('sms_line_id')->nullable()->constrained('smsgate_lines')->onDelete('set null');
            
            // بيانات الرسالة الأساسية
            $table->string('phone_number')->index();
            $table->text('message_body');
            $table->string('direction')->index(); // incoming, outgoing
            
            // حالات التسليم والعمليات التشغيلية
            $table->string('status')->default('queued')->index(); // queued, processing, sending, sent, delivered, failed, cancelled, expired
            $table->string('failure_reason')->nullable();
            $table->integer('retry_count')->default(0);
            
            // المعرف المحلي على الهاتف لمنع الازدواجية والمطابقة (Idempotency Ref)
            $table->string('message_ref')->nullable()->index();
            
            // الطوابع الزمنية الخاصة بالشبكة
            $table->timestamp('sent_at')->nullable()->index();
            $table->timestamp('delivered_at')->nullable()->index();
            
            $table->timestamps();
            $table->softDeletes();
            
            // الفهارس
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('smsgate_messages');
    }
};
