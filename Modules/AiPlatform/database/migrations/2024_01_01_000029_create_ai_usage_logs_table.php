<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('ai_usage_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id')->nullable();
            $table->unsignedBigInteger('ai_execution_request_id')->nullable();
            $table->unsignedBigInteger('ai_provider_account_id')->nullable();
            $table->unsignedBigInteger('ai_model_id')->nullable();
            $table->unsignedBigInteger('ai_agent_id')->nullable();
            $table->unsignedBigInteger('ai_conversation_id')->nullable();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('capability_key', 100);
            $table->integer('input_tokens')->default(0);
            $table->integer('output_tokens')->default(0);
            $table->integer('total_tokens')->default(0);
            $table->decimal('input_cost', 12, 6)->default(0);
            $table->decimal('output_cost', 12, 6)->default(0);
            $table->decimal('total_cost', 12, 6)->default(0);
            $table->integer('latency_ms')->nullable();
            $table->boolean('is_successful')->default(false);
            $table->timestamp('created_at')->nullable();
            
            $table->foreign('ai_provider_account_id')->references('id')->on('ai_provider_accounts')->nullOnDelete();
            $table->foreign('ai_model_id')->references('id')->on('ai_models')->nullOnDelete();
            $table->foreign('ai_agent_id')->references('id')->on('ai_agents')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_usage_logs');
    }
};
