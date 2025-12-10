<?php

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
        Schema::table('cash_boxes', function (Blueprint $table) {
            // إضافة حقل التفعيل البوليني الجديد، نضعه بعد is_default
            // القيمة الافتراضية true (نشط) للسجلات الجديدة
            $table->boolean('is_active')->default(true)->after('is_default');
        });
        
        // ملاحظة: بمجرد تطبيق هذا الميجريشن، سيتم تعيين is_active = true
        // لجميع السجلات الموجودة مسبقًا تلقائياً بواسطة القيمة الافتراضية.
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('cash_boxes', function (Blueprint $table) {
            $table->dropColumn('is_active');
        });
    }
};