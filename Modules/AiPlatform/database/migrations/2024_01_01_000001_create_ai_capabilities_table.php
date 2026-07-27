<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('ai_capabilities', function (Blueprint $table) {
            $table->id();
            $table->string('key', 100)->unique();
            $table->string('label', 150);
            $table->string('type', 50);
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->smallInteger('sort_order')->default(0);
            $table->timestamps();
            
            $table->index('type');
            $table->index('is_active');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_capabilities');
    }
};
