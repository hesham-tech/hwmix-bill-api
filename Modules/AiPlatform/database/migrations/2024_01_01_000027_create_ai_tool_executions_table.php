<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('ai_tool_executions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id')->nullable();
            $table->unsignedBigInteger('ai_tool_id')->nullable();
            $table->unsignedBigInteger('ai_execution_request_id')->nullable();
            $table->unsignedBigInteger('ai_conversation_id')->nullable();
            $table->json('input_params')->nullable();
            $table->json('output_data')->nullable();
            $table->boolean('is_successful')->default(false);
            $table->text('error_message')->nullable();
            $table->integer('execution_ms')->nullable();
            $table->unsignedBigInteger('executed_by')->nullable();
            $table->timestamp('created_at')->nullable();

            $table->foreign('ai_tool_id')->references('id')->on('ai_tools')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_tool_executions');
    }
};
