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
        Schema::create('tasks', function (Blueprint $seq) {
            $seq->id();
            $seq->foreignId('company_id')->constrained()->onDelete('cascade');
            $seq->string('title');
            $seq->text('description')->nullable();
            $seq->enum('priority', ['urgent', 'high', 'medium', 'low'])->default('medium');
            $seq->enum('status', ['pending', 'doing', 'review', 'completed', 'cancelled'])->default('pending');
            $seq->dateTime('deadline')->nullable();
            $seq->integer('progress')->default(0);
            $seq->foreignId('created_by')->constrained('users')->onDelete('cascade');
            $seq->timestamps();
            $seq->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tasks');
    }
};
