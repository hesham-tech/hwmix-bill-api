<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * القوائم التي تحتاج لتحديث حقل branch_id
     */
    protected $tables = [
        'users',
        'invoices',
        'cash_boxes',
        'transactions',
        'warehouses',
        'expenses',
        'financial_ledger',
        'revenues',
        'installments',
        'installment_payments',
        'stocks',
        'activity_logs',
        'error_reports'
    ];

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. جلب كل الشركات
        $companies = DB::table('companies')->get();

        foreach ($companies as $company) {
            // 2. التحقق من وجود فرع افتراضي لهذه الشركة
            $defaultBranchId = DB::table('branches')
                ->where('company_id', $company->id)
                ->where('is_default', true)
                ->value('id');

            // 3. إذا لم يوجد، نقوم بإنشائه
            if (!$defaultBranchId) {
                $defaultBranchId = DB::table('branches')->insertGetId([
                    'name' => 'الفرع الرئيسي',
                    'company_id' => $company->id,
                    'is_default' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            // 4. تحديث كل الجداول المرتبطة بهذه الشركة والتي تحتوي على branch_id فارغ
            foreach ($this->tables as $tableName) {
                if (Schema::hasTable($tableName) && Schema::hasColumn($tableName, 'branch_id')) {
                    DB::table($tableName)
                        ->where('company_id', $company->id)
                        ->whereNull('branch_id')
                        ->update(['branch_id' => $defaultBranchId]);
                }
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // لا يوجد حاجة لعكس البيانات يدوياً لأن حذف عمود branch_id سيقوم بالمهمة
        // ولكن يمكن تصفير الحقل إذا لزم الأمر
        foreach ($this->tables as $tableName) {
            if (Schema::hasTable($tableName) && Schema::hasColumn($tableName, 'branch_id')) {
                DB::table($tableName)->update(['branch_id' => null]);
            }
        }
    }
};
