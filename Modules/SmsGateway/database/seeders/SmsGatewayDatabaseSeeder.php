<?php
// البذر الأولي لقاعدة البيانات الخاص بموديول بوابة الرسائل والـ SMS.

namespace Modules\SmsGateway\database\seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Models\Company;

class SmsGatewayDatabaseSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // تنظيف كاش الصلاحيات
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        // الصلاحيات الخاصة بموديول SMS Gateway
        $permissions = [
            // بوابات الأجهزة والشرائح
            'sms_gateway.page',
            'sms_gateway.view_all',
            'sms_gateway.view_self',
            'sms_gateway.create',
            'sms_gateway.update_all',
            'sms_gateway.delete_all',
            
            // إدارة رسائل SMS
            'sms_messages.page',
            'sms_messages.view_all',
            'sms_messages.view_self',
            'sms_messages.create',
            'sms_messages.update_all',
            'sms_messages.delete_all',
        ];

        foreach ($permissions as $permissionName) {
            Permission::firstOrCreate([
                'name' => $permissionName,
                'guard_name' => 'web'
            ]);
        }

        // إلحاق الصلاحيات لدور "مدير شركة" (إن وجد)
        $companyModel = app(Company::class);
        $firstCompany = $companyModel::first();
        $companyId = $firstCompany ? $firstCompany->id : null;

        if (config('permission.teams') && $companyId) {
            setPermissionsTeamId($companyId);
        }

        $roleModel = app(Role::class);
        $managerRole = $roleModel::where('name', 'مدير شركة')->first();
        
        if ($managerRole) {
            $managerRole->givePermissionTo($permissions);
        }
    }
}
