<?php

namespace Database\Seeders;

// Seeder آمن لإضافة صلاحيات الحماية الأمنية الجديدة دون المساس بالصلاحيات الموجودة

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Illuminate\Support\Facades\DB;

class SecurityPermissionsSeeder extends Seeder
{
    private array $newPermissions = [
        ['name' => 'invoices.view_profit',       'label' => 'عرض هامش الربح في الفاتورة'],
        ['name' => 'reports.view_liquidity',      'label' => 'عرض تقارير السيولة والأرباح (Admin Dashboard)'],
        ['name' => 'cash_boxes.view_balance',     'label' => 'عرض رصيد الخزينة'],
        ['name' => 'warehouses.view_stock_value', 'label' => 'عرض القيمة المالية للمخزون'],
        ['name' => 'products.update_prices',      'label' => 'تعديل أسعار وتكاليف المنتجات'],
        ['name' => 'users.assign_permissions',    'label' => 'تعيين وتعديل صلاحيات وأدوار المستخدمين'],
    ];

    public function run(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $added = 0;
        $skipped = 0;

        foreach ($this->newPermissions as $permData) {
            $permission = Permission::firstOrCreate(
                ['name' => $permData['name'], 'guard_name' => 'web'],
                ['created_at' => now(), 'updated_at' => now()]
            );

            if ($permission->wasRecentlyCreated) {
                $this->command->info('  Added: ' . $permData['name']);
                $added++;
            } else {
                $this->command->line('  Exists: ' . $permData['name']);
                $skipped++;
            }
        }

        $this->grantToCompanyAdmins();

        app()[PermissionRegistrar::class]->forgetCachedPermissions();
        $this->command->info('Done: ' . $added . ' added, ' . $skipped . ' skipped.');
    }

    /**
     * منح الصلاحيات الجديدة للمستخدمين الذين يملكون admin.company.
     * يتم تمرير company_id الصحيح لكل مستخدم من نفس سجل model_has_permissions.
     */
    private function grantToCompanyAdmins(): void
    {
        $adminPerm = Permission::where('name', 'admin.company')->where('guard_name', 'web')->first();
        if (!$adminPerm) return;

        // جلب مستخدمي admin.company مع company_id الخاصة بهم
        $adminEntries = DB::table('model_has_permissions')
            ->where('permission_id', $adminPerm->id)
            ->where('model_type', 'App\Models\User')
            ->get(['model_id', 'company_id']);

        if ($adminEntries->isEmpty()) return;

        $newPermIds = Permission::whereIn('name', array_column($this->newPermissions, 'name'))
            ->where('guard_name', 'web')
            ->pluck('id')
            ->toArray();

        $inserted = 0;
        foreach ($adminEntries as $entry) {
            foreach ($newPermIds as $permId) {
                $exists = DB::table('model_has_permissions')->where([
                    'permission_id' => $permId,
                    'model_type'    => 'App\Models\User',
                    'model_id'      => $entry->model_id,
                    'company_id'    => $entry->company_id,
                ])->exists();

                if (!$exists) {
                    DB::table('model_has_permissions')->insert([
                        'permission_id' => $permId,
                        'model_type'    => 'App\Models\User',
                        'model_id'      => $entry->model_id,
                        'company_id'    => $entry->company_id,
                    ]);
                    $inserted++;
                }
            }
        }

        if ($inserted > 0) {
            $this->command->info("  Granted $inserted permission entries to company admins.");
        }
    }
}