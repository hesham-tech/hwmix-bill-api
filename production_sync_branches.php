<?php

/**
 * Production Script: Branch Management Synchronization
 * هذا السكربت مخصص للتشغيل لمرة واحدة على السيرفر لتصحيح كافة السجلات القديمة
 */

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

echo "--- [START] Branch Data Synchronization ---\n";

$tables = [
    'users', 'invoices', 'cash_boxes', 'transactions', 'warehouses',
    'expenses', 'financial_ledger', 'revenues', 'installments',
    'installment_payments', 'stocks', 'activity_logs', 'error_reports'
];

try {
    DB::beginTransaction();

    $companies = DB::table('companies')->get();
    echo "Found " . $companies->count() . " companies.\n";

    foreach ($companies as $company) {
        // 1. التأكد من وجود فرع رئيسي
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
            echo "[Company: {$company->name}] Created default branch.\n";
        }

        // 2. تحديث كافة الجداول المرتبطة
        foreach ($tables as $tableName) {
            if (Schema::hasTable($tableName) && Schema::hasColumn($tableName, 'branch_id')) {
                $affected = DB::table($tableName)
                    ->where('company_id', $company->id)
                    ->whereNull('branch_id')
                    ->update(['branch_id' => $defaultBranchId]);
                
                if ($affected > 0) {
                    echo "  -> Updated {$affected} records in [{$tableName}].\n";
                }
            }
        }
    }

    DB::commit();
    echo "--- [SUCCESS] All records synchronized successfully. ---\n";

} catch (\Exception $e) {
    DB::rollBack();
    echo "--- [ERROR] Sync failed: " . $e->getMessage() . "\n";
}
