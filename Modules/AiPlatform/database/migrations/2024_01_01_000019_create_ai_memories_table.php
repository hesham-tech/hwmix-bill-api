<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('ai_memories', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id')->nullable();
            $table->string('type', 20);
            $table->string('scope_type', 30)->nullable();
            $table->unsignedBigInteger('scope_id')->nullable();
            $table->string('key', 150);
            $table->longText('value');
            $table->string('value_type', 20);
            $table->tinyInteger('importance')->default(1);
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_memories');
    }
};
