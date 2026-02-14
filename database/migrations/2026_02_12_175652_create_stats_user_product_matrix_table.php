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
        if (!Schema::hasTable('stats_user_product_matrix')) {
            Schema::create('stats_user_product_matrix', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('user_id')->index();
                $table->unsignedBigInteger('product_id')->index();
                $table->unsignedBigInteger('company_id')->index();

                // User-Product Specific Metrics
                $table->decimal('total_quantity', 15, 2)->default(0);
                $table->decimal('total_spent', 15, 2)->default(0);
                $table->unsignedBigInteger('purchase_count')->default(0);

                $table->timestamp('last_purchased_at')->nullable();
                $table->timestamps();

                $table->unique(['user_id', 'product_id', 'company_id'], 'user_product_company_unique');
                $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
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
        Schema::dropIfExists('stats_user_product_matrix');
    }
};
