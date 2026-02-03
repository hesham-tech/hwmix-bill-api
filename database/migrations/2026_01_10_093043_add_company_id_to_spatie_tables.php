<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $tableNames = config('permission.table_names');
        $columnNames = config('permission.column_names');
        $teams = config('permission.teams');

        if (!$teams) {
            return;
        }

        // 1. model_has_permissions
        $tableName1 = $tableNames['model_has_permissions'];
        if (!Schema::hasColumn($tableName1, 'company_id')) {
            try {
                Schema::table($tableName1, function (Blueprint $table) use ($columnNames) {
                    $table->unsignedBigInteger('company_id')->nullable()->after($columnNames['model_morph_key']);
                });
            } catch (\Throwable $e) {
            }
        }

        // Re-create Primary Key if needed
        try {
            Schema::table($tableName1, function (Blueprint $table) use ($columnNames) {
                // Check if current PK is already the extended one
                $indices = DB::select("SHOW INDEX FROM " . $columnNames['model_has_permissions']); // Re-using variable logic
            });
        } catch (\Throwable $e) {
            // Let's do a more direct approach
            try {
                DB::statement("ALTER TABLE `{$tableName1}` DROP PRIMARY KEY, ADD PRIMARY KEY (`permission_id`, `{$columnNames['model_morph_key']}`, `model_type`, `company_id`) USING BTREE;");
            } catch (\Throwable $e) {
            }
        }

        // 2. model_has_roles
        $tableName2 = $tableNames['model_has_roles'];
        if (!Schema::hasColumn($tableName2, 'company_id')) {
            try {
                Schema::table($tableName2, function (Blueprint $table) use ($columnNames) {
                    $table->unsignedBigInteger('company_id')->nullable()->after($columnNames['model_morph_key']);
                });
            } catch (\Throwable $e) {
            }
        }

        try {
            DB::statement("ALTER TABLE `{$tableName2}` DROP PRIMARY KEY, ADD PRIMARY KEY (`role_id`, `{$columnNames['model_morph_key']}`, `model_type`, `company_id`) USING BTREE;");
        } catch (\Throwable $e) {
        }

        // 3. roles
        $tableName3 = $tableNames['roles'];
        if (Schema::hasTable($tableName3)) {
            Schema::table($tableName3, function (Blueprint $table) use ($tableName3) {
                $indices = DB::select("SHOW INDEX FROM " . $tableName3);
                $indexNames = array_map(fn($index) => $index->Key_name, $indices);

                if (in_array($tableName3 . '_name_guard_name_unique', $indexNames)) {
                    try {
                        $table->dropUnique($tableName3 . '_name_guard_name_unique');
                    } catch (\Throwable $e) {
                    }
                }

                if (!in_array($tableName3 . '_name_guard_name_company_id_unique', $indexNames)) {
                    try {
                        $table->unique(['name', 'guard_name', 'company_id'], $tableName3 . '_name_guard_name_company_id_unique');
                    } catch (\Throwable $e) {
                    }
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // No need for a complex down in a dry run, but keeping standard
    }
};
