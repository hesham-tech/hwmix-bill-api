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
        if (!Schema::hasTable('stats_products_summary')) {
            Schema::create('stats_products_summary', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('product_id')->unique()->index();
                $table->unsignedBigInteger('company_id')->index();

                // Aggregated Metrics
                $table->decimal('total_sold_quantity', 15, 2)->default(0);
                $table->decimal('total_revenue', 15, 2)->default(0);
                $table->decimal('total_profit', 15, 2)->default(0);
                $table->unsignedBigInteger('total_orders_count')->default(0);

                $table->timestamp('last_sold_at')->nullable();
                $table->timestamps();

                $table->foreign('product_id')->references('id')->on('products')->onDelete('cascade');
                $table->foreign('company_id')->references('id')->on('companies')->onDelete('cascade');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('stats_products_summary');
    }
};
