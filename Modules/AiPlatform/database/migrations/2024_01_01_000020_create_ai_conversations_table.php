<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('ai_conversations', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id')->nullable();
            $table->char('ulid', 26)->unique();
            $table->foreignId('ai_agent_id')->constrained('ai_agents')->cascadeOnDelete();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('title', 200)->nullable();
            $table->string('status', 20);
            $table->integer('total_tokens')->default(0);
            $table->decimal('total_cost', 12, 6)->default(0);
            $table->smallInteger('message_count')->default(0);
            $table->json('metadata')->nullable();
            $table->timestamp('last_message_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_conversations');
    }
};
