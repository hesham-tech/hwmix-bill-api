<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

//   هجرة قاعدة البيانات لإضافة حقل is_global لجداول التنبيهات والربط
return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('mail_settings', function (Blueprint $table) {
            $table->boolean('is_global')->default(false)->after('company_id');
        });

        Schema::table('whatsapp_settings', function (Blueprint $table) {
            $table->boolean('is_global')->default(false)->after('company_id');
        });

        Schema::table('notification_templates', function (Blueprint $table) {
            $table->boolean('is_global')->default(false)->after('company_id');
        });

        Schema::table('notification_workflows', function (Blueprint $table) {
            $table->boolean('is_global')->default(false)->after('company_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('mail_settings', function (Blueprint $table) {
            $table->dropColumn('is_global');
        });

        Schema::table('whatsapp_settings', function (Blueprint $table) {
            $table->dropColumn('is_global');
        });

        Schema::table('notification_templates', function (Blueprint $table) {
            $table->dropColumn('is_global');
        });

        Schema::table('notification_workflows', function (Blueprint $table) {
            $table->dropColumn('is_global');
        });
    }
};
