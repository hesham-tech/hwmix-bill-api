<?php

// تعليق عربي: هجرة لإنشاء جدول تخزين وسجلات ملفات الوسائط المرفوعة لكل شركة.

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('media_files', function (Blueprint $table) {
            $table->id();
            
            $table->string('filename'); // الاسم الجديد للملف على الخادم
            $table->string('original_name'); // الاسم الأصلي للملف المرفوع
            $table->text('file_path'); // المسار النسبي للملف في التخزين
            $table->unsignedBigInteger('file_size'); // الحجم بالبايت
            $table->string('mime_type'); // نوع الملف (image/webp, application/pdf, etc.)
            
            $table->foreignId('company_id')->constrained()->onDelete('cascade');
            $table->foreignId('created_by')->nullable()->constrained('users')->onDelete('set null');

            $table->softDeletes();
            $table->timestamps();
            
            $table->index('company_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('media_files');
    }
};
