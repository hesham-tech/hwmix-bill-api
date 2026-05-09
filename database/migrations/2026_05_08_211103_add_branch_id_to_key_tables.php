<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * القوائم التي تحتاج لإضافة حقل branch_id
     */
    protected $tables = [
        'users',
        'invoices',
        'cash_boxes',
        'transactions',
        'warehouses',
        'expenses',
        'financial_ledger'
    ];

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        foreach ($this->tables as $tableName) {
            if (Schema::hasTable($tableName)) {
                Schema::table($tableName, function (Blueprint $table) use ($tableName) {
                    if (!Schema::hasColumn($tableName, 'branch_id')) {
                        $table->foreignId('branch_id')->nullable()->after('company_id')->constrained('branches')->onDelete('set null');
                        $table->index('branch_id');
                    }
                });
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        foreach ($this->tables as $tableName) {
            if (Schema::hasTable($tableName)) {
                Schema::table($tableName, function (Blueprint $table) use ($tableName) {
                    if (Schema::hasColumn($tableName, 'branch_id')) {
                        $table->dropForeign([$tableName . '_branch_id_foreign']);
                        $table->dropColumn('branch_id');
                    }
                });
            }
        }
    }
};
