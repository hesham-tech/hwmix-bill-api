<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('ai_router_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id')->nullable();
            $table->unsignedBigInteger('ai_execution_request_id')->nullable();
            $table->string('capability_key', 100);
            $table->unsignedBigInteger('selected_account_id')->nullable();
            $table->unsignedBigInteger('selected_model_id')->nullable();
            $table->string('selection_reason', 200)->nullable();
            $table->json('accounts_considered')->nullable();
            $table->smallInteger('decision_ms')->nullable();
            $table->tinyInteger('attempt_number')->default(1);
            $table->timestamp('created_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_router_logs');
    }
};
