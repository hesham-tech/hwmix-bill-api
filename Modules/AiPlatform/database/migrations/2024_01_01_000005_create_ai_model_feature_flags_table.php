<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('ai_model_feature_flags', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ai_model_id')->constrained('ai_models')->cascadeOnDelete();
            $table->string('flag', 50);
            $table->boolean('is_enabled')->default(true);
            $table->timestamps();

            $table->unique(['ai_model_id', 'flag']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_model_feature_flags');
    }
};
