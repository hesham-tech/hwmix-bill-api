<?php
// ترحيل إنشاء جدول نتائج التحليل المنظم الشامل لمنصة الذكاء الاصطناعي ai_analysis_results.

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_analysis_results', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->onDelete('cascade');

            $table->string('source_type', 50)->default('direct')->index(); // hwnix_cash_message, email, whatsapp_message, document, direct
            $table->unsignedBigInteger('source_id')->nullable()->index();

            $table->string('analysis_type', 50)->default('general')->index(); // financial_sms, bank_statement, invoice_ocr, email, whatsapp
            $table->string('provider', 50)->default('general')->index(); // vodafone_cash, orange_cash, cib, instapay, general
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

            $table->index(['company_id', 'analysis_type', 'status'], 'idx_ai_analysis_lookup');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_analysis_results');
    }
};
