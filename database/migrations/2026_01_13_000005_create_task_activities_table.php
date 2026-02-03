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
        Schema::create('task_activities', function (Blueprint $seq) {
            $seq->id();
            $seq->foreignId('task_id')->constrained()->onDelete('cascade');
            $seq->foreignId('user_id')->constrained()->onDelete('cascade');
            $seq->string('type'); // comment, status_change, progress_update, etc.
            $seq->text('content')->nullable();
            $seq->json('metadata')->nullable(); // For storing old/new values
            $seq->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('task_activities');
    }
};
