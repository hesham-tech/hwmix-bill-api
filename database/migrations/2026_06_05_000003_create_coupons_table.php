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
        Schema::create('coupons', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('discount_type'); // percent | fixed
            
            // استخدام decimal(18,2) للأموال فقط طبقاً لقواعد GEMINI.md
            $table->decimal('value', 18, 2);
            
            $table->dateTime('starts_at');
            $table->dateTime('ends_at');
            $table->integer('max_uses')->default(0); // 0 يعني غير محدود
            $table->integer('used_count')->default(0);
            $table->boolean('is_cumulative')->default(false); // قابل للدمج مع خصومات أخرى
            $table->boolean('status')->default(true);
            
            // قواعد GEMINI.md للتوافق الكامل
            $table->unsignedBigInteger('company_id')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            
            $table->softDeletes(); // استخدام soft deletes للبيانات الحساسة طبقاً لقواعد GEMINI.md
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('coupons');
    }
};
