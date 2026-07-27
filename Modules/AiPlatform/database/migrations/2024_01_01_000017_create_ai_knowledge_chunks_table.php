<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('ai_knowledge_chunks', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id')->nullable();
            $table->foreignId('ai_knowledge_base_id')->constrained('ai_knowledge_bases')->cascadeOnDelete();
            $table->string('source_label', 300)->nullable();
            $table->longText('content');
            $table->json('metadata')->nullable();
            $table->smallInteger('token_count')->nullable();
            $table->integer('chunk_index');
            $table->timestamp('created_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_knowledge_chunks');
    }
};
