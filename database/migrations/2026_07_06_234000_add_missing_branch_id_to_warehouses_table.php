<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

// إضافة حقل الفرع المفقود لجدول المستودعات وتحديث البيانات القديمة
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (Schema::hasTable('warehouses')) {
            Schema::table('warehouses', function (Blueprint $table) {
                if (!Schema::hasColumn('warehouses', 'branch_id')) {
                    $table->foreignId('branch_id')->nullable()->after('company_id')->constrained('branches')->onDelete('set null');
                    $table->index('branch_id');
                }
            });

            // تحديث المستودعات التي ليس لها فرع بالفرع الافتراضي للشركة
            $companies = DB::table('companies')->get();
            foreach ($companies as $company) {
                $defaultBranchId = DB::table('branches')
                    ->where('company_id', $company->id)
                    ->where('is_default', true)
                    ->value('id');

                if (!$defaultBranchId) {
                    $defaultBranchId = DB::table('branches')->insertGetId([
                        'name' => 'الفرع الرئيسي',
                        'company_id' => $company->id,
                        'is_default' => true,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }

                DB::table('warehouses')
                    ->where('company_id', $company->id)
                    ->whereNull('branch_id')
                    ->update(['branch_id' => $defaultBranchId]);
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('warehouses')) {
            Schema::table('warehouses', function (Blueprint $table) {
                if (Schema::hasColumn('warehouses', 'branch_id')) {
                    $table->dropForeign(['warehouses_branch_id_foreign']);
                    $table->dropColumn('branch_id');
                }
            });
        }
    }
};
