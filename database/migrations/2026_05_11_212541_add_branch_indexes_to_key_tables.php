<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $tables = [
            'users',
            'invoices',
            'cash_boxes',
            'transactions',
            'warehouses',
            'expenses',
            'financial_ledger',
            'products'
        ];

        foreach ($tables as $tableName) {
            if (Schema::hasColumn($tableName, 'branch_id')) {
                $indexName = $tableName . '_branch_id_index';
                
                // التحقق من وجود الفهرس قبل إضافته لتجنب الأخطاء
                $indices = DB::select("SHOW INDEX FROM " . $tableName . " WHERE Key_name = '" . $indexName . "'");
                
                if (empty($indices)) {
                    Schema::table($tableName, function (Blueprint $table) use ($indexName) {
                        $table->index('branch_id', $indexName);
                    });
                }
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // No complex down logic needed for indexes in this environment
    }
};
