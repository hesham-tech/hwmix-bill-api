<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('ai_workflow_steps', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ai_workflow_id')->constrained('ai_workflows')->cascadeOnDelete();
            $table->tinyInteger('step_order');
            $table->string('label', 100);
            $table->string('type', 30);
            $table->string('capability_key', 100)->nullable();
            $table->unsignedBigInteger('ai_tool_id')->nullable();
            $table->unsignedBigInteger('ai_prompt_id')->nullable();
            $table->json('input_mapping')->nullable();
            $table->string('output_key', 100)->nullable();
            $table->json('condition')->nullable();
            $table->string('on_failure', 20)->default('fail');
            $table->tinyInteger('max_retries')->default(0);
            $table->smallInteger('timeout_seconds')->nullable();
            $table->timestamp('created_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_workflow_steps');
    }
};
