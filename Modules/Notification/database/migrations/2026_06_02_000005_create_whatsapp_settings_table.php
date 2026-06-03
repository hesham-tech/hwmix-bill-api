<?php

// تعليق عربي: هجرة لإنشاء جدول إعدادات حسابات الواتساب لتخزين بيانات الربط بـ Meta Cloud API لكل شركة.

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('whatsapp_settings', function (Blueprint $table) {
            $table->id();
            $table->string('title'); // عنوان الحساب (تسمية توضيحية)
            $table->string('phone_number_id'); // معرف رقم الهاتف من فيسبوك
            $table->string('waba_id'); // معرف حساب واتساب بزنس WABA
            $table->text('access_token'); // رمز الوصول طويل المدى مشفر
            $table->boolean('is_active')->default(true);
            $table->boolean('is_default')->default(false);

            $table->foreignId('company_id')->constrained()->onDelete('cascade');
            $table->foreignId('created_by')->nullable()->constrained('users')->onDelete('set null');

            $table->softDeletes();
            $table->timestamps();

            $table->index(['company_id', 'is_active']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('whatsapp_settings');
    }
};
