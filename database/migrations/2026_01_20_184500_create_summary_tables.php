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
        Schema::create('daily_sales_summary', function (Blueprint $table) {
            $table->id();
            $table->date('date')->index();
            $table->unsignedBigInteger('company_id')->index();

            // Revenues and Counts
            $table->decimal('total_revenue', 15, 2)->default(0)->comment('إجمالي الإيرادات');
            $table->integer('sales_count')->default(0)->comment('عدد المبيعات');

            // Costs and Profits
            $table->decimal('total_cogs', 15, 2)->default(0)->comment('تكلفة البضاعة المباعة');
            $table->decimal('total_expenses', 15, 2)->default(0)->comment('إجمالي المصاريف التشغيلية');
            $table->decimal('gross_profit', 15, 2)->default(0)->comment('الربح الإجمالي (Revenue - COGS)');
            $table->decimal('net_profit', 15, 2)->default(0)->comment('صافي الربح');

            $table->timestamps();

            $table->unique(['date', 'company_id']);
            $table->foreign('company_id')->references('id')->on('companies')->onDelete('cascade');
        });

        Schema::create('monthly_sales_summary', function (Blueprint $table) {
            $table->id();
            $table->string('year_month', 7)->index(); // YYYY-MM
            $table->unsignedBigInteger('company_id')->index();

            $table->decimal('total_revenue', 15, 2)->default(0);
            $table->decimal('total_cogs', 15, 2)->default(0);
            $table->decimal('total_expenses', 15, 2)->default(0);
            $table->decimal('net_profit', 15, 2)->default(0);
            $table->integer('sales_count')->default(0);

            $table->timestamps();

            $table->unique(['year_month', 'company_id']);
            $table->foreign('company_id')->references('id')->on('companies')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('monthly_sales_summary');
        Schema::dropIfExists('daily_sales_summary');
    }
};
