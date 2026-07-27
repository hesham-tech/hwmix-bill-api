<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('ai_prompt_variables', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ai_prompt_id')->constrained('ai_prompts')->cascadeOnDelete();
            $table->string('name', 100);
            $table->string('type', 20);
            $table->boolean('is_required')->default(true);
            $table->string('default_value', 500)->nullable();
            $table->string('description', 300)->nullable();
            $table->timestamp('created_at')->nullable();

            $table->unique(['ai_prompt_id', 'name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_prompt_variables');
    }
};
