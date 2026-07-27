<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('ai_embeddings', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id')->nullable();
            $table->foreignId('ai_knowledge_chunk_id')->constrained('ai_knowledge_chunks')->cascadeOnDelete();
            $table->unsignedBigInteger('ai_model_id')->nullable();
            $table->json('vector');
            $table->smallInteger('vector_dimensions');
            $table->timestamp('created_at')->nullable();

            $table->unique('ai_knowledge_chunk_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_embeddings');
    }
};
