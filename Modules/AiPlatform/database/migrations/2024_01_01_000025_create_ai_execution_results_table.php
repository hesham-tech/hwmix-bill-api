<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('ai_execution_results', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ai_execution_request_id')->constrained('ai_execution_requests')->cascadeOnDelete();
            $table->unsignedBigInteger('company_id')->nullable();
            $table->unsignedBigInteger('ai_provider_account_id')->nullable();
            $table->unsignedBigInteger('ai_model_id')->nullable();
            $table->longText('output_data')->nullable();
            $table->string('output_type', 20)->nullable();
            $table->integer('input_tokens')->default(0);
            $table->integer('output_tokens')->default(0);
            $table->integer('total_tokens')->default(0);
            $table->integer('latency_ms')->nullable();
            $table->tinyInteger('attempt_number')->default(1);
            $table->boolean('is_successful')->default(false);
            $table->string('error_code', 50)->nullable();
            $table->text('error_message')->nullable();
            $table->json('tool_calls')->nullable();
            $table->timestamp('created_at')->nullable();

            $table->unique('ai_execution_request_id');
            // Setting null constraint via blueprint requires proper logic or manual handling. For now just standard nullable column.
            $table->foreign('ai_model_id')->references('id')->on('ai_models')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_execution_results');
    }
};
