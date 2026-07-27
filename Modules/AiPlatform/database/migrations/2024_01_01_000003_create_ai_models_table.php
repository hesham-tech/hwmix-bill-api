<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('ai_models', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ai_provider_id')->constrained('ai_providers')->cascadeOnDelete();
            $table->string('model_id', 100);
            $table->string('label', 150);
            $table->string('version', 50)->nullable();
            $table->integer('max_context_tokens')->nullable();
            $table->integer('max_output_tokens')->nullable();
            $table->decimal('input_price_per_1k', 10, 8)->default(0);
            $table->decimal('output_price_per_1k', 10, 8)->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['ai_provider_id', 'model_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_models');
    }
};
