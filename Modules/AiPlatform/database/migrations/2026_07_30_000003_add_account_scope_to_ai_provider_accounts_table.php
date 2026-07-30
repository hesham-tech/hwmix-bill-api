<?php
// ترحيل إضافة حقل نطاق الحساب account_scope بدلاً من الاعتماد على is_system البسيط.

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ai_provider_accounts', function (Blueprint $table) {
            $table->string('account_scope', 30)->default('SYSTEM')->after('company_id')->index();
        });

        // تحديث النطاقات الحالية تلقائياً لضمان سلامة التوافق التراجعي
        DB::table('ai_provider_accounts')
            ->whereNull('company_id')
            ->orWhere('company_id', 1)
            ->update(['account_scope' => 'SYSTEM']);

        DB::table('ai_provider_accounts')
            ->whereNotNull('company_id')
            ->where('company_id', '>', 1)
            ->update(['account_scope' => 'COMPANY']);
    }

    public function down(): void
    {
        Schema::table('ai_provider_accounts', function (Blueprint $table) {
            $table->dropColumn('account_scope');
        });
    }
};
