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
        $indexExists = $this->hasIndex('unique_default_cashbox_for_user_company');

        Schema::table('cash_boxes', function (Blueprint $table) use ($indexExists) {
            // Create fallback indexes for foreign keys before dropping the unique key
            $table->index('user_id');
            $table->index('company_id');

            // 1. Drop the old unique constraint if it exists
            if ($indexExists) {
                $table->dropUnique('unique_default_cashbox_for_user_company');
            }

            // 2. Create the new unique constraint including branch_id
            $table->unique(
                ['user_id', 'company_id', 'branch_id', 'cash_box_type_id', 'is_default'],
                'unique_default_cashbox_for_user_company_branch'
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $indexExists = $this->hasIndex('unique_default_cashbox_for_user_company_branch');

        Schema::table('cash_boxes', function (Blueprint $table) use ($indexExists) {
            // Drop the new unique constraint if it exists
            if ($indexExists) {
                $table->dropUnique('unique_default_cashbox_for_user_company_branch');
            }

            // Restore the old unique constraint
            $table->unique(
                ['user_id', 'company_id', 'cash_box_type_id', 'is_default'],
                'unique_default_cashbox_for_user_company'
            );

            // Drop fallback indexes
            $table->dropIndex(['user_id']);
            $table->dropIndex(['company_id']);
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
