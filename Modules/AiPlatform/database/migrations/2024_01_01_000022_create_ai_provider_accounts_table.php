<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('ai_provider_accounts', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id')->nullable();
            $table->foreignId('ai_provider_id')->constrained('ai_providers')->cascadeOnDelete();
            $table->string('label', 150);
            $table->text('api_key_encrypted');
            $table->string('api_key_hint', 10);
            $table->tinyInteger('api_key_version')->default(1);
            $table->string('custom_base_url', 300)->nullable();
            $table->json('extra_headers')->nullable();
            $table->bigInteger('quota_tokens_per_day')->nullable();
            $table->bigInteger('quota_tokens_per_month')->nullable();
            $table->smallInteger('quota_requests_per_min')->nullable();
            $table->bigInteger('used_tokens_today')->default(0);
            $table->bigInteger('used_tokens_this_month')->default(0);
            $table->tinyInteger('priority')->default(0);
            $table->boolean('is_active')->default(true);
            $table->string('health_status', 20)->default('unknown');
            $table->timestamp('health_checked_at')->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('rotation_reminder_at')->nullable();
            $table->tinyInteger('failed_attempts')->default(0);
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_provider_accounts');
    }
};
