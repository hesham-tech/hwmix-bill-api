<?php

// تعليق عربي: هجرة لإنشاء جدول عمليات الدفع الإلكتروني لتسجيل وتتبع المعاملات وحالتها المالية.

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('payment_transactions', function (Blueprint $table) {
            $table->id();
            
            $table->foreignId('payment_gateway_id')->nullable()->constrained('payment_gateways')->onDelete('set null');
            
            // Polymorphic relation to payable entity (e.g. Invoice, Subscription)
            $table->string('payable_type');
            $table->unsignedBigInteger('payable_id');
            
            $table->decimal('amount', 18, 2); // المبلغ المستحق للدفع
            $table->string('currency', 3)->default('USD'); // العملة
            $table->string('status')->default('pending'); // pending, completed, failed, refunded
            
            $table->string('gateway_reference')->nullable(); // الرقم المرجعي من البوابة الخارجية
            $table->json('payload')->nullable(); // تفاصيل العملية الكاملة
            
            $table->foreignId('company_id')->constrained()->onDelete('cascade');
            $table->foreignId('branch_id')->nullable()->constrained('branches')->onDelete('set null');
            $table->foreignId('created_by')->nullable()->constrained('users')->onDelete('set null');

            $table->softDeletes();
            $table->timestamps();
            
            $table->index(['company_id', 'branch_id']);
            $table->index(['payable_type', 'payable_id']);
            $table->index('gateway_reference');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payment_transactions');
    }
};
