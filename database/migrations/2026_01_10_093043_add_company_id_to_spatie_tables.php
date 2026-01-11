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
        if (!Schema::hasColumn($tableNames['model_has_permissions'], 'company_id')) {
            Schema::table($tableNames['model_has_permissions'], function (Blueprint $table) use ($columnNames) {
                $table->unsignedBigInteger('company_id')->nullable()->after($columnNames['model_morph_key']);
            });

            // Re-create Primary Key
            Schema::table($tableNames['model_has_permissions'], function (Blueprint $table) use ($columnNames) {
                $table->dropPrimary();
                $table->primary([$columnNames['model_morph_key'], 'permission_id', 'model_type', 'company_id'], 'model_has_permissions_permission_model_type_company_primary');
            });
        }

        // 2. model_has_roles
        if (!Schema::hasColumn($tableNames['model_has_roles'], 'company_id')) {
            Schema::table($tableNames['model_has_roles'], function (Blueprint $table) use ($columnNames) {
                $table->unsignedBigInteger('company_id')->nullable()->after($columnNames['model_morph_key']);
            });

            // Re-create Primary Key
            Schema::table($tableNames['model_has_roles'], function (Blueprint $table) use ($columnNames) {
                $table->dropPrimary();
                $table->primary([$columnNames['model_morph_key'], 'role_id', 'model_type', 'company_id'], 'model_has_roles_role_model_type_company_primary');
            });
        }

        // 3. roles - Check if we need to update unique index
        // We know roles_name_guard_name_company_id_unique probably exists, 
        // let's just make sure roles_name_guard_name_unique is gone if it exists.
        Schema::table($tableNames['roles'], function (Blueprint $table) use ($tableNames) {
            $indices = DB::select("SHOW INDEX FROM " . $tableNames['roles']);
            $indexNames = array_map(fn($index) => $index->Key_name, $indices);

            if (in_array($tableNames['roles'] . '_name_guard_name_unique', $indexNames)) {
                $table->dropUnique($tableNames['roles'] . '_name_guard_name_unique');
            }

            if (!in_array($tableNames['roles'] . '_name_guard_name_company_id_unique', $indexNames)) {
                $table->unique(['name', 'guard_name', 'company_id'], $tableNames['roles'] . '_name_guard_name_company_id_unique');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $tableNames = config('permission.table_names');
        $columnNames = config('permission.column_names');

        Schema::table($tableNames['model_has_roles'], function (Blueprint $table) use ($tableNames, $columnNames) {
            $table->dropPrimary('model_has_roles_role_model_type_company_primary');
            $table->dropColumn('company_id');
            $table->primary([$columnNames['model_morph_key'], 'role_id', 'model_type'], 'model_has_roles_role_model_type_primary');
        });

        Schema::table($tableNames['model_has_permissions'], function (Blueprint $table) use ($tableNames, $columnNames) {
            $table->dropPrimary('model_has_permissions_permission_model_type_company_primary');
            $table->dropColumn('company_id');
            $table->primary([$columnNames['model_morph_key'], 'permission_id', 'model_type'], 'model_has_permissions_permission_model_type_primary');
        });
    }
};
