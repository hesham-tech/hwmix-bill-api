<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('ai_plugins', function (Blueprint $table) {
            $table->id();
            $table->string('key', 100)->unique();
            $table->string('label', 150);
            $table->string('version', 20);
            $table->text('description')->nullable();
            $table->string('provider_class', 300);
            $table->boolean('is_active')->default(true);
            $table->timestamp('registered_at')->nullable();
            $table->timestamp('updated_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_plugins');
    }
};
