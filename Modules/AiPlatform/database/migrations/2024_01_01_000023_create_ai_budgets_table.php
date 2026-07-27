<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('ai_budgets', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id')->nullable();
            $table->string('scope_type', 20);
            $table->unsignedBigInteger('scope_id')->nullable();
            $table->string('period', 10);
            $table->bigInteger('limit_tokens')->nullable();
            $table->decimal('limit_cost', 12, 6)->nullable();
            $table->bigInteger('used_tokens')->default(0);
            $table->decimal('used_cost', 12, 6)->default(0);
            $table->tinyInteger('alert_at_percent')->default(80);
            $table->timestamp('reset_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['company_id', 'scope_type', 'scope_id', 'period'], 'ai_budgets_unique_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_budgets');
    }
};
