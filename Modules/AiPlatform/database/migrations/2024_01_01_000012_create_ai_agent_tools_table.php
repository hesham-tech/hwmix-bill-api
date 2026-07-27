<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('ai_agent_tools', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id')->nullable();
            $table->foreignId('ai_agent_id')->constrained('ai_agents')->cascadeOnDelete();
            $table->foreignId('ai_tool_id')->constrained('ai_tools')->cascadeOnDelete();
            $table->boolean('is_active')->default(true);
            $table->timestamp('created_at')->nullable();

            $table->unique(['ai_agent_id', 'ai_tool_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_agent_tools');
    }
};
