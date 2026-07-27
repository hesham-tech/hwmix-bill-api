<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('ai_model_capabilities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ai_model_id')->constrained('ai_models')->cascadeOnDelete();
            $table->string('ai_capability_key', 100);
            $table->timestamp('created_at')->nullable();

            $table->unique(['ai_model_id', 'ai_capability_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_model_capabilities');
    }
};
