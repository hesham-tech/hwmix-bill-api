<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('ai_messages', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id')->nullable();
            $table->foreignId('ai_conversation_id')->constrained('ai_conversations')->cascadeOnDelete();
            $table->unsignedBigInteger('ai_execution_result_id')->nullable();
            $table->string('role', 20);
            $table->longText('content')->nullable();
            $table->string('content_type', 20);
            $table->string('tool_call_id', 100)->nullable();
            $table->string('tool_name', 100)->nullable();
            $table->integer('input_tokens')->default(0);
            $table->integer('output_tokens')->default(0);
            $table->decimal('cost', 12, 6)->default(0);
            $table->unsignedBigInteger('ai_model_id')->nullable();
            $table->timestamp('created_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_messages');
    }
};
