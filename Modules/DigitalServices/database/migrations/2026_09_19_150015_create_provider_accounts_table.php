<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('provider_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignId('service_provider_id')->constrained('service_providers')->cascadeOnDelete();
            $table->string('name');
            $table->unsignedBigInteger('cash_box_id'); // الربط المحاسبي بالخزينة (CashBox)
            $table->unsignedBigInteger('hwnix_cash_financial_account_id')->nullable(); // الربط برصيد الأندرويد الفعلي
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
            
            // Explicit constraints to avoid cross-module table name mismatches if any
            $table->foreign('cash_box_id')->references('id')->on('cash_boxes')->cascadeOnDelete();
            $table->foreign('hwnix_cash_financial_account_id', 'hwnix_cash_fin_acc_fk')->references('id')->on('hwnix_cash_financial_accounts')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('provider_accounts');
    }
};
