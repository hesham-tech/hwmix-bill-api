<?php
// ترحيل إضافة معرف التتبع الموحد وبصمة التخزين المؤقت لجدول ai_analysis_results.

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ai_analysis_results', function (Blueprint $table) {
            $table->string('correlation_id', 50)->nullable()->after('company_id')->index();
            $table->string('fingerprint', 64)->nullable()->after('provider')->index();
        });
    }

    public function down(): void
    {
        Schema::table('ai_analysis_results', function (Blueprint $table) {
            $table->dropColumn(['correlation_id', 'fingerprint']);
        });
    }
};
