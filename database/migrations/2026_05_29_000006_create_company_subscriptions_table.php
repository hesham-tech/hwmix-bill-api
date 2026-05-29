<?php

// تعليق عربي: هجرة لإنشاء جدول اشتراكات الشركات المشتركة بالمنصة (SaaS Subscriptions) مع حفظ التواريخ والقيود المفروضة عليها.

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('company_subscriptions', function (Blueprint $table) {
            $table->id();
            
            $table->foreignId('company_id')->constrained('companies')->onDelete('cascade');
            $table->foreignId('plan_id')->constrained('plans')->onDelete('restrict');
            
            $table->dateTime('starts_at');
            $table->dateTime('ends_at')->nullable();
            $table->dateTime('trial_ends_at')->nullable();
            
            $table->decimal('price', 15, 2)->default(0);
            $table->string('billing_cycle', 50)->default('monthly'); // monthly, yearly, lifetime, trial
            $table->string('status', 50)->default('trial'); // active, expired, canceled, trial, suspended
            
            // حقول تخصيص القيود الاستثنائية للشركة بشكل مستقل عن خطتها
            $table->integer('max_users')->nullable();
            $table->integer('max_products')->nullable();
            $table->integer('max_invoices')->nullable();
            $table->json('features')->nullable();
            
            $table->foreignId('created_by')->nullable()->constrained('users')->onDelete('set null');
            
            $table->softDeletes();
            $table->timestamps();
            
            $table->index('company_id');
            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('company_subscriptions');
    }
};
