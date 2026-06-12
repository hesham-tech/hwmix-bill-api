<?php

//   هجرة تعديل جدول إعدادات البريد لإضافة حقول العنوان وتفعيل الحساب وتحديد الافتراضي للشركة.

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('mail_settings', function (Blueprint $table) {
            $table->string('title')->nullable()->after('id');
            $table->boolean('is_active')->default(true)->after('mail_from_name');
            $table->boolean('is_default')->default(false)->after('is_active');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('mail_settings', function (Blueprint $table) {
            $table->dropColumn(['title', 'is_active', 'is_default']);
        });
    }
};
