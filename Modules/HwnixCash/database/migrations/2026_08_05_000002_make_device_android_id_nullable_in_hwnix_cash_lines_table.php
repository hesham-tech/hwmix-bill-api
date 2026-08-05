<?php
// تعديل جدول خطوط الكاش لجعل حقل device_android_id قابلاً لاستقبال قيم null لتمكين عزل/بيع الأجهزة بدون خطوط.

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('hwnix_cash_lines', function (Blueprint $table) {
            $table->string('device_android_id', 100)->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('hwnix_cash_lines', function (Blueprint $table) {
            $table->string('device_android_id', 100)->nullable(false)->change();
        });
    }
};
