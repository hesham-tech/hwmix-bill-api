<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

// حذف القيد الفريد الافتراضي لصناديق المستخدم المالية لتمكين التحديث المرن
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if ($this->hasIndex('unique_default_cashbox_for_user_company_branch')) {
            Schema::table('cash_boxes', function (Blueprint $table) {
                $table->dropUnique('unique_default_cashbox_for_user_company_branch');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('cash_boxes', function (Blueprint $table) {
            $table->unique(
                ['user_id', 'company_id', 'branch_id', 'cash_box_type_id', 'is_default'],
                'unique_default_cashbox_for_user_company_branch'
            );
        });
    }

    /**
     * Check if an index exists on the cash_boxes table.
     */
    private function hasIndex(string $indexName): bool
    {
        $driver = Schema::getConnection()->getDriverName();
        if ($driver === 'mysql') {
            $results = DB::select("SHOW INDEX FROM cash_boxes WHERE Key_name = ?", [$indexName]);
            return !empty($results);
        } elseif ($driver === 'sqlite') {
            $results = DB::select("SELECT name FROM sqlite_master WHERE type='index' AND name = ?", [$indexName]);
            return !empty($results);
        }
        return false;
    }
};
