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
        Schema::table('transactions', function (Blueprint $table) {
            $table->decimal('employee_balance_before', 15, 2)->nullable()->after('balance_after');
            $table->decimal('employee_balance_after', 15, 2)->nullable()->after('employee_balance_before');
            
            $table->decimal('client_balance_before', 15, 2)->nullable()->after('employee_balance_after');
            $table->decimal('client_balance_after', 15, 2)->nullable()->after('client_balance_before');
            
            $table->foreignId('source_invoice_id')->nullable()->after('client_balance_after')->constrained('invoices')->onDelete('set null');
            $table->foreignId('source_installment_id')->nullable()->after('source_invoice_id')->constrained('installments')->onDelete('set null');
            
            $table->boolean('is_transfer')->default(false)->after('source_installment_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropForeign(['source_invoice_id']);
            $table->dropForeign(['source_installment_id']);
            
            $table->dropColumn([
                'employee_balance_before',
                'employee_balance_after',
                'client_balance_before',
                'client_balance_after',
                'source_invoice_id',
                'source_installment_id',
                'is_transfer'
            ]);
        });
    }
};
