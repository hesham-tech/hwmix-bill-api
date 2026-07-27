<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('ai_workflow_runs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id')->nullable();
            $table->foreignId('ai_workflow_id')->constrained('ai_workflows')->cascadeOnDelete();
            $table->char('ulid', 26)->unique();
            $table->json('input_data')->nullable();
            $table->json('context')->nullable();
            $table->string('status', 20);
            $table->tinyInteger('current_step')->default(0);
            $table->tinyInteger('total_steps')->default(0);
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->tinyInteger('error_step')->nullable();
            $table->text('error_message')->nullable();
            $table->integer('total_tokens')->default(0);
            $table->decimal('total_cost', 12, 6)->default(0);
            $table->unsignedBigInteger('triggered_by')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_workflow_runs');
    }
};
