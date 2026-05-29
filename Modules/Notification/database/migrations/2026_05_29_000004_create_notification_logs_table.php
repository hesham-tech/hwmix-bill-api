<?php

// تعليق عربي: هجرة لإنشاء جدول سجلات التنبيهات الصادرة من النظام لتسجيل تاريخ وحالة الإرسال.

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('notification_logs', function (Blueprint $table) {
            $table->id();
            
            $table->string('type'); // email, whatsapp, sms
            $table->string('recipient'); // البريد أو رقم الجوال المستلم
            $table->string('title')->nullable(); // العنوان أو اسم القالب
            $table->text('content'); // نص الرسالة بالكامل
            $table->string('status')->default('sent'); // sent, failed
            $table->text('error_message')->nullable(); // تفاصيل الفشل إن وجدت
            
            $table->foreignId('company_id')->constrained()->onDelete('cascade');
            $table->foreignId('branch_id')->nullable()->constrained('branches')->onDelete('set null');
            $table->foreignId('created_by')->nullable()->constrained('users')->onDelete('set null');

            $table->timestamps();
            
            $table->index(['company_id', 'branch_id']);
            $table->index('type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('notification_logs');
    }
};
