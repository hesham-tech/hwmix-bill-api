<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('ai_agents', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id')->nullable();
            $table->string('key', 100);
            $table->string('label', 150);
            $table->text('description')->nullable();
            $table->unsignedBigInteger('ai_prompt_id')->nullable();
            $table->string('preferred_capability', 100)->nullable();
            $table->unsignedBigInteger('preferred_model_id')->nullable();
            $table->integer('max_tokens_per_req')->nullable();
            $table->decimal('budget_per_day', 12, 6)->nullable();
            $table->decimal('temperature', 3, 2)->nullable();
            $table->decimal('top_p', 3, 2)->nullable();
            $table->boolean('memory_enabled')->default(false);
            $table->unsignedBigInteger('knowledge_base_id')->nullable();
            $table->string('plugin_key', 100)->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedBigInteger('created_by')->nullable();
            $table->softDeletes();
            $table->timestamps();

            $table->unique(['company_id', 'key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_agents');
    }
};
