<?php
// ترحيل لإنشاء جدول حفظ نواتج تحليل الرسائل القصيرة كأصول دائمة بالنظام.

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hwnix_cash_sms_analysis_results', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->onDelete('cascade');
            $table->foreignId('message_id')->constrained('hwnix_cash_messages')->onDelete('cascade');

            $table->string('provider', 50)->default('general')->index();
            $table->string('message_type', 50)->default('unknown')->index();
            $table->string('status', 30)->default('valid')->index(); // valid, invalid, needs_review

            $table->unsignedSmallInteger('confidence_score')->default(100);
            $table->string('schema_version', 20)->default('1.0');
            $table->string('prompt_version', 20)->default('1.0');
            $table->string('parser_version', 20)->default('1.0.0');
            $table->string('ai_model', 100)->nullable();

            $table->json('normalized_json')->nullable();
            $table->text('raw_response')->nullable();
            $table->json('execution_metadata')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['company_id', 'message_type', 'status'], 'idx_hwnix_cash_sms_analysis');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hwnix_cash_sms_analysis_results');
    }
};
