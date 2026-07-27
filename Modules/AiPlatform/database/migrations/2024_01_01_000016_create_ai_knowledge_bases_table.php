<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('ai_knowledge_bases', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id')->nullable();
            $table->unsignedBigInteger('ai_agent_id')->nullable();
            $table->string('label', 150);
            $table->text('description')->nullable();
            $table->string('source_type', 30);
            $table->smallInteger('chunk_size')->default(1000);
            $table->smallInteger('chunk_overlap')->default(200);
            $table->string('embedding_model', 100)->nullable();
            $table->integer('total_chunks')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_indexed_at')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_knowledge_bases');
    }
};
