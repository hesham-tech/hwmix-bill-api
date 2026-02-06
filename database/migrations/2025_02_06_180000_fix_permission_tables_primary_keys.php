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
        $tableNames = config('permission.table_names');
        $columnNames = config('permission.column_names');
        $teamKey = $columnNames['team_foreign_key'] ?? 'company_id';

        // 1. Drop Foreign Keys first (essential in some DB engines before altering PK)
        Schema::table($tableNames['model_has_permissions'], function (Blueprint $table) {
            try {
                $table->dropForeign(['permission_id']);
            } catch (\Exception $e) {
            }
        });

        Schema::table($tableNames['model_has_roles'], function (Blueprint $table) {
            try {
                $table->dropForeign(['role_id']);
            } catch (\Exception $e) {
            }
        });

        // 2. Drop existing primary keys
        try {
            DB::statement("ALTER TABLE `{$tableNames['model_has_permissions']}` DROP PRIMARY KEY");
        } catch (\Exception $e) {
        }

        try {
            DB::statement("ALTER TABLE `{$tableNames['model_has_roles']}` DROP PRIMARY KEY");
        } catch (\Exception $e) {
        }

        // 3. Data Cleanup: Assign NULL company_id to default company (1)
        DB::table($tableNames['model_has_permissions'])->whereNull($teamKey)->update([$teamKey => 1]);
        DB::table($tableNames['model_has_roles'])->whereNull($teamKey)->update([$teamKey => 1]);

        // 4. Re-add primary keys with team field
        Schema::table($tableNames['model_has_permissions'], function (Blueprint $table) use ($teamKey) {
            $table->primary([$teamKey, 'permission_id', 'model_id', 'model_type'], 'model_has_permissions_permission_model_type_primary');
        });

        Schema::table($tableNames['model_has_roles'], function (Blueprint $table) use ($teamKey) {
            $table->primary([$teamKey, 'role_id', 'model_id', 'model_type'], 'model_has_roles_role_model_type_primary');
        });

        // 5. Re-add Foreign Keys
        Schema::table($tableNames['model_has_permissions'], function (Blueprint $table) use ($tableNames) {
            $table->foreign('permission_id')
                ->references('id')
                ->on($tableNames['permissions'])
                ->onDelete('cascade');
        });

        Schema::table($tableNames['model_has_roles'], function (Blueprint $table) use ($tableNames) {
            $table->foreign('role_id')
                ->references('id')
                ->on($tableNames['roles'])
                ->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $tableNames = config('permission.table_names');

        Schema::table($tableNames['model_has_permissions'], function (Blueprint $table) {
            $table->dropPrimary('model_has_permissions_permission_model_type_primary');
            $table->primary(['permission_id', 'model_id', 'model_type'], 'model_has_permissions_permission_model_type_primary');
        });

        Schema::table($tableNames['model_has_roles'], function (Blueprint $table) {
            $table->dropPrimary('model_has_roles_role_model_type_primary');
            $table->primary(['role_id', 'model_id', 'model_type'], 'model_has_roles_role_model_type_primary');
        });
    }
};
