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
        Schema::create('financial_ledger', function (Blueprint $table) {
            $table->id();
            $table->dateTime('entry_date')->index();
            $table->enum('type', ['debit', 'credit'])->comment('مدين أو دائن');
            $table->decimal('amount', 15, 2);
            $table->string('description')->nullable();

            // الربط مع المصدر (قاعدة بيانات مرنة)
            $table->string('source_type')->index()->comment('invoice, expense, transaction, etc');
            $table->unsignedBigInteger('source_id')->index();

            // نوع الحساب (تبسيط لشجرة الحسابات)
            $table->enum('account_type', ['revenue', 'expense', 'asset', 'liability', 'equity'])->index();

            $table->unsignedBigInteger('company_id')->index();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();

            $table->foreign('company_id')->references('id')->on('companies')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('financial_ledger');
    }
};
