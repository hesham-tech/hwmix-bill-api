<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('ai_prompt_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ai_prompt_id')->constrained('ai_prompts')->cascadeOnDelete();
            $table->unsignedBigInteger('company_id')->nullable();
            $table->tinyInteger('version');
            $table->string('locale', 10);
            $table->longText('template');
            $table->text('notes')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamp('created_at')->nullable();

            $table->unique(['ai_prompt_id', 'version', 'locale']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_prompt_versions');
    }
};
