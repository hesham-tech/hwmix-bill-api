<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('error_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->onDelete('set null');
            $table->foreignId('company_id')->nullable()->constrained()->onDelete('set null');
            $table->string('type')->default('error'); // error, warning, crash
            $table->text('message');
            $table->longText('stack_trace')->nullable();
            $table->string('url')->nullable();
            $table->string('browser')->nullable();
            $table->string('os')->nullable();
            $table->json('payload')->nullable(); // Extended data (Vue state, inputs, etc.)
            $table->string('status')->default('pending'); // pending, investigating, resolved, closed
            $table->string('severity')->default('medium'); // low, medium, high, critical
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('error_reports');
    }
};
