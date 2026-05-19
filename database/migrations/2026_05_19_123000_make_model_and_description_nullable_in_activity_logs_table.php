<?php

// تعديل أعمدة جدول سجل الأنشطة لتصبح قابلة للقيمة الفارغة لتجنب مشاكل القيود وقواعد البيانات Strict

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('activity_logs', function (Blueprint $table) {
            $table->string('model')->nullable()->change();
            $table->text('description')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('activity_logs', function (Blueprint $table) {
            $table->string('model')->nullable(false)->change();
            $table->text('description')->nullable(false)->change();
        });
    }
};
