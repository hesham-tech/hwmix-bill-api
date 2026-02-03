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
        Schema::create('task_group_user', function (Blueprint $seq) {
            $seq->id();
            $seq->foreignId('task_group_id')->constrained()->onDelete('cascade');
            $seq->foreignId('user_id')->constrained()->onDelete('cascade');
            $seq->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('task_group_user');
    }
};
