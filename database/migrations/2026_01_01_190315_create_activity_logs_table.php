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
        Schema::create('activity_logs', function (Blueprint $table) {
            $table->id();

            // User who performed the action
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();

            // Company context
            $table->foreignId('company_id')->nullable()->constrained('companies')->onDelete('cascade');

            // Subject (what was affected) - Polymorphic
            $table->string('subject_type')->nullable(); // Invoice, Product, User, etc.
            $table->unsignedBigInteger('subject_id')->nullable();
            $table->index(['subject_type', 'subject_id']);

            // Action type
            $table->string('action', 50); // created, updated, deleted, viewed, exported, etc.

            // Description in Arabic
            $table->text('description');

            // Data changes (JSON)
            $table->json('old_values')->nullable();
            $table->json('new_values')->nullable();

            // Request metadata
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();

            // Additional context
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('url')->nullable();
            $table->json('metadata')->nullable(); // Any extra data

            $table->timestamps();

            // Indexes for performance
            $table->index('user_id');
            $table->index('company_id');
            $table->index('action');
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('activity_logs');
    }
};
