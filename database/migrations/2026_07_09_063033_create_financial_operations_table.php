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
        Schema::create('financial_operations', function (Blueprint $table) {
            $table->uuid('id')->primary(); // المعرف الفريد للعملية
            $table->unsignedBigInteger('company_id')->index();
            $table->string('type'); // invoice_sale_creation, payment_receipt, etc.
            $table->string('status')->default('active'); // active, reversed
            $table->decimal('amount', 18, 2);
            $table->string('source_type')->nullable(); // Polymorphic source (Invoice, Payment, Expense)
            $table->unsignedBigInteger('source_id')->nullable();
            $table->json('metadata')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();

            // الفهارس الخارجية والتنظيم المحاسبي للعزل
            $table->index(['company_id', 'type']);
            $table->index(['source_type', 'source_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('financial_operations');
    }
};
