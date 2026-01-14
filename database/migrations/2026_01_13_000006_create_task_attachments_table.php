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
        Schema::create('task_attachments', function (Blueprint $seq) {
            $seq->id();
            $seq->foreignId('task_id')->constrained()->onDelete('cascade');
            $seq->foreignId('user_id')->constrained()->onDelete('cascade');
            $seq->string('file_path');
            $seq->string('file_name');
            $seq->string('file_type')->nullable();
            $seq->integer('file_size')->nullable();
            $seq->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('task_attachments');
    }
};
