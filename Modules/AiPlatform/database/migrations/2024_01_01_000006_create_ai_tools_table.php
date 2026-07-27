<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('ai_tools', function (Blueprint $table) {
            $table->id();
            $table->string('key', 100)->unique();
            $table->string('label', 150);
            $table->text('description');
            $table->string('class_name', 300);
            $table->json('schema');
            $table->string('plugin_key', 100)->nullable();
            $table->string('required_permission', 150)->nullable();
            $table->boolean('is_async')->default(false);
            $table->smallInteger('timeout_seconds')->default(30);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_tools');
    }
};
