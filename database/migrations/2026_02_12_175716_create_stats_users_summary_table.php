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
        if (!Schema::hasTable('stats_users_summary')) {
            Schema::create('stats_users_summary', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('user_id')->unique()->index();
                $table->unsignedBigInteger('company_id')->index();

                // Aggregated Metrics
                $table->decimal('total_spent', 15, 2)->default(0);
                $table->unsignedBigInteger('orders_count')->default(0);
                $table->unsignedBigInteger('favorite_category_id')->nullable()->index();

                // Advanced Scoring
                $table->decimal('rfm_score', 8, 2)->default(0)->comment('Recency, Frequency, Monetary Score');

                $table->timestamp('last_order_at')->nullable();
                $table->timestamps();

                $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
                $table->foreign('company_id')->references('id')->on('companies')->onDelete('cascade');
                $table->foreign('favorite_category_id')->references('id')->on('categories')->onDelete('set null');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('stats_users_summary');
    }
};
