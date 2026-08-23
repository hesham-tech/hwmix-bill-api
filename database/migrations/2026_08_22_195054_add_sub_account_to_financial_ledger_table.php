<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('financial_ledger', function (Blueprint $table) {
            $table->string('sub_account_type')->nullable()->after('account_type')->comment('Polymorphic relation to specific account (e.g., CashBox, User, Warehouse)');
            $table->unsignedBigInteger('sub_account_id')->nullable()->after('sub_account_type');
            $table->string('journal_entry_id')->nullable()->after('financial_operation_id')->index();
        });
    }

    public function down(): void
    {
        Schema::table('financial_ledger', function (Blueprint $table) {
            $table->dropColumn(['sub_account_type', 'sub_account_id', 'journal_entry_id']);
        });
    }
};
