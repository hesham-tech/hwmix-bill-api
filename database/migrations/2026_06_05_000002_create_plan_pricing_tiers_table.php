<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('plan_pricing_tiers', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('plan_id');
            $table->integer('min_months');
            $table->integer('max_months')->nullable(); // null يعني ما لا نهاية
            
            // استخدام decimal(18,2) للأموال فقط طبقاً لقواعد GEMINI.md
            $table->decimal('price_per_month', 18, 2);
            $table->decimal('discount_percent', 5, 2)->default(0.00);
            
            // قواعد GEMINI.md للتوافق الكامل
            $table->unsignedBigInteger('company_id')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            
            $table->timestamps();
            
            $table->foreign('plan_id')->references('id')->on('plans')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('plan_pricing_tiers');
    }
};
