<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('ai_audit_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id')->nullable();
            $table->string('event', 50);
            $table->string('actor_type', 30)->nullable();
            $table->unsignedBigInteger('actor_id')->nullable();
            $table->string('subject_type', 50)->nullable();
            $table->unsignedBigInteger('subject_id')->nullable();
            $table->json('payload')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->timestamp('created_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_audit_logs');
    }
};
