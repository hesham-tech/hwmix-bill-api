<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('ai_prompts', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id')->nullable();
            $table->string('key', 150);
            $table->string('type', 30);
            $table->string('label', 200);
            $table->string('capability_key', 100)->nullable();
            $table->unsignedBigInteger('ai_agent_id')->nullable();
            $table->tinyInteger('current_version')->default(1);
            $table->boolean('is_active')->default(true);
            $table->unsignedBigInteger('approved_by')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('tested_at')->nullable();
            $table->string('plugin_key', 100)->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->softDeletes();
            $table->timestamps();

            $table->unique(['company_id', 'key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_prompts');
    }
};
