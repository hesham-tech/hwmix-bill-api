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
        Schema::table('financial_ledger', function (Blueprint $table) {
            $table->uuid('financial_operation_id')->nullable()->index()->after('company_id');
        });

        Schema::table('transactions', function (Blueprint $table) {
            $table->uuid('financial_operation_id')->nullable()->index()->after('company_id');
        });

        Schema::table('invoice_payments', function (Blueprint $table) {
            $table->uuid('financial_operation_id')->nullable()->index()->after('company_id');
        });

        Schema::table('cash_reconciliations', function (Blueprint $table) {
            $table->uuid('financial_operation_id')->nullable()->index()->after('company_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('financial_ledger', function (Blueprint $table) {
            $table->dropColumn('financial_operation_id');
        });

        Schema::table('transactions', function (Blueprint $table) {
            $table->dropColumn('financial_operation_id');
        });

        Schema::table('invoice_payments', function (Blueprint $table) {
            $table->dropColumn('financial_operation_id');
        });

        Schema::table('cash_reconciliations', function (Blueprint $table) {
            $table->dropColumn('financial_operation_id');
        });
    }
};
