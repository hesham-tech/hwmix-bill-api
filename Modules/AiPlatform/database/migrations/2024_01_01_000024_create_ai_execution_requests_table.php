<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('ai_execution_requests', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id')->nullable();
            $table->char('ulid', 26)->unique();
            $table->string('capability_key', 100);
            $table->string('source_type', 30);
            $table->unsignedBigInteger('source_id')->nullable();
            $table->unsignedBigInteger('ai_agent_id')->nullable();
            $table->unsignedBigInteger('ai_prompt_id')->nullable();
            $table->unsignedBigInteger('requested_by')->nullable();
            $table->json('input_data')->nullable();
            $table->string('status', 20);
            $table->boolean('queued')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_execution_requests');
    }
};
