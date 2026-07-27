<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('ai_providers', function (Blueprint $table) {
            $table->id();
            $table->string('key', 50)->unique();
            $table->string('label', 100);
            $table->string('type', 30);
            $table->string('driver_class', 200);
            $table->string('base_url', 300)->nullable();
            $table->string('docs_url', 300)->nullable();
            $table->string('logo_url', 300)->nullable();
            $table->boolean('is_active')->default(true);
            $table->smallInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_providers');
    }
};
