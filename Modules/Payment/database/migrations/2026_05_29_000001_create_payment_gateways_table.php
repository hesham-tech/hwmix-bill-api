<?php

// تعليق عربي: هجرة لإنشاء جدول بوابات الدفع التي تربط الشركات بإعداداتها الخاصة لتمكين الدفع الإلكتروني.

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('payment_gateways', function (Blueprint $table) {
            $table->id();
            $table->string('name'); // الاسم التعريفي
            $table->string('driver'); // stripe, paymob, etc.
            $table->text('config'); // الإعدادات مشفرة
            $table->boolean('is_active')->default(true); // نشط أم لا
            $table->boolean('is_test_mode')->default(false); // بيئة تجريبية أم حية

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
        Schema::dropIfExists('payment_gateways');
    }
};
