<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('ai_budget_alerts', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id')->nullable();
            $table->foreignId('ai_budget_id')->constrained('ai_budgets')->cascadeOnDelete();
            $table->string('alert_type', 30);
            $table->tinyInteger('used_percent');
            $table->decimal('used_cost', 12, 6)->default(0);
            $table->timestamp('notified_at')->nullable();
            $table->timestamp('created_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_budget_alerts');
    }
};
