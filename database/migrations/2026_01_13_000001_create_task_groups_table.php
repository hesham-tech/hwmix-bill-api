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
        Schema::create('task_groups', function (Blueprint $seq) {
            $seq->id();
            $seq->foreignId('company_id')->constrained()->onDelete('cascade');
            $seq->string('name');
            $seq->string('description')->nullable();
            $seq->string('color')->default('primary');
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
        Schema::dropIfExists('task_groups');
    }
};
