<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\User;
use App\Models\Company;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;  // تأكد من استخدام موديل الصلاحيات الصحيح

class PermissionsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        // ✅ تنظيف كل أنواع الكاش (Application + Config + Route + View + Compiled)
        \Artisan::call('cache:clear');
        \Artisan::call('config:clear');
        \Artisan::call('route:clear');
        \Artisan::call('view:clear');
        \Artisan::call('clear-compiled');


        // تم إزالة السطر التالي للحفاظ على الصلاحيات الحالية في بيئة الإنتاج
        // Permission::query()->delete();

        // ✅ تنظيف كاش الصلاحيات الخاص بـ Spatie
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        // جلب جميع تعريفات الصلاحيات من ملف config/permissions_keys.php
        $permissionsConfig = config('permissions_keys');

        // مصفوفة لتخزين جميع مفاتيح الصلاحيات التي سيتم إضافتها
        $permissionsToSeed = [];

        // المرور على كل كيان (entity) وكل فعل (action) داخل ملف الصلاحيات
        foreach ($permissionsConfig as $entity => $actions) {
            foreach ($actions as $key => $actionData) {
                if ($key === 'name')
                    continue;
                // التأكد من أن المفتاح 'key' موجود لضمان عدم وجود أخطاء
                if (isset($actionData['key'])) {
                    $permissionsToSeed[] = [
                        'name' => $actionData['key'],
                        'guard_name' => 'web',
                        'created_at' => now(),  // إضافة timestamp
                        'updated_at' => now(),  // إضافة timestamp
                    ];
                }
            }
        }

        // إدراج الصلاحيات بأمان باستخدام firstOrCreate لتجنب مسح القديم
        foreach ($permissionsToSeed as $perm) {
            Permission::firstOrCreate(
                ['name' => $perm['name']],
                ['guard_name' => $perm['guard_name']]
            );
        }

        // تشغيل Seeder الخاص بالمستخدمين أولاً إذا كان ينشئ المستخدمين
        $this->call(RolesAndPermissionsSeeder::class);

        // جلب أول مستخدم موجود بعد تنفيذ Seeder
        $admin = User::first();
        $userId = $admin ? $admin->id : null;
        $this->command->info('قائمة الإيميلات الموجودة: ' . User::pluck('email')->implode(', '));
        if (!$userId) {
            $this->command->error('لا يوجد مستخدم في قاعدة البيانات بعد تنفيذ Seeder المستخدمين. الرجاء التأكد من منطق إنشاء المستخدم.');
            return;
        }

        // ----------- إنشاء الأدوار وتعيين الصلاحيات تلقائياً -----------
        $roleModel = app(Role::class);
        $roles = [
            [
                'name' => 'مدير شركة',
                'actions' => [
                    'change_active_company',
                    'page',
                    'stocks',
                    'stocks.page',
                    'view_all',
                    'view_children',
                    'view_self',
                    'create',
                    'update_all',
                    'update_children',
                    'update_self',
                    'delete_all',
                    'delete_children',
                    'delete_self',
                    'view_wholesale_price',
                    'view_purchase_price',
                    'manual_adjustment',
                    // الجديد: التقارير والمالية
                    'sales',
                    'stock',
                    'profit',
                    'expenses',
                    'cash_flow',
                    'tax',
                    'export',
                    'print_labels',
                ],
            ],
            [
                'name' => 'ادارة جميع السجلات',
                'actions' => ['page', 'view_all', 'update_all', 'delete_all'],
            ],
            [
                'name' => 'ادارة سجلاته فقط',
                'actions' => ['page', 'view_self', 'update_self', 'delete_self'],
            ],
            [
                'name' => 'ادارة سجلاته وسجلات التابعين له',
                'actions' => ['page', 'view_children', 'update_children', 'delete_children'],
            ],
            [
                'name' => 'اضافة سجلات',
                'actions' => ['page', 'create'],
            ],

        ];
        $companyModel = app(Company::class);
        $firstCompany = $companyModel::first();
        $companyIdForRoles = $firstCompany ? $firstCompany->id : null;

        // إخبار النظام برقم الشركة قبل إنشاء الأدوار ومنح الصلاحيات (Spatie Teams)
        if (config('permission.teams') && $companyIdForRoles) {
            setPermissionsTeamId($companyIdForRoles);
        }

        foreach ($roles as $roleData) {
            $role = $roleModel::firstOrCreate([
                'name' => $roleData['name'],
                'company_id' => $companyIdForRoles,
                'created_by' => $userId
            ], ['guard_name' => 'web']);
            $permissions = [];
            foreach ($permissionsConfig as $entity => $actions) {
                foreach ($actions as $key => $actionData) {
                    if ($key === 'name')
                        continue;
                    if (isset($actionData['key']) && in_array($key, $roleData['actions'])) {
                        $permissions[] = $actionData['key'];
                    }
                }
            }
            // بدلاً من syncPermissions الذي يحذف الصلاحيات المخصصة الأخرى، نستخدم givePermissionTo لإضافة النواقص فقط
            $role->givePermissionTo($permissions);

        }

        // ربط كل شركة بكل الأدوار الأربعة بأمان (دون استخدام truncate الذي يمسح كل شيء)
        $companyRoleTable = 'role_company';
        $companies = $companyModel::all();
        $roleIds = $roleModel::whereIn('name', array_column($roles, 'name'))->pluck('id', 'name');
        $now = now();
        
        foreach ($companies as $company) {
            foreach ($roles as $roleData) {
                $roleId = $roleIds[$roleData['name']];
                
                // التأكد من عدم وجود الربط مسبقاً قبل الإضافة
                $exists = \DB::table($companyRoleTable)
                    ->where('company_id', $company->id)
                    ->where('role_id', $roleId)
                    ->exists();
                    
                if (!$exists) {
                    \DB::table($companyRoleTable)->insert([
                        'company_id' => $company->id,
                        'role_id' => $roleId,
                        'created_by' => $userId,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                }
            }
        }
        $this->command->info('Roles and company-role relations seeded successfully!');
    }
}
