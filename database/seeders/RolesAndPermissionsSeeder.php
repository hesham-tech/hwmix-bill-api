<?php

namespace Database\Seeders;

use App\Models\CashBox;
use App\Models\CashBoxType;
use App\Models\Company;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Artisan;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RolesAndPermissionsSeeder extends Seeder
{
    public function run()
    {
        $permissions = Permission::all();

        // نستخدم الشركة الأولى الموجودة بالفعل (التي أنشأها CompanySeeder)
        $company = Company::first();

        $admin = User::where('email', 'admin@admin.com')->first();
        if (!$admin) {
            $this->createSystemOwner($permissions, $company);
        } else {
            // تحديث بيانات الدخول للتأكد من مطابقتها للسيدر
            $admin->update([
                'nickname' => 'مدير النظام',
                'full_name' => 'هشام محمد',
                'phone' => '01006444991',
                'password' => bcrypt('12345678'),
            ]);

            // إخبار النظام برقم الشركة قبل مزامنة الصلاحيات (Spatie Teams)
            if (config('permission.teams') && $company) {
                setPermissionsTeamId($company->id);
            }
            $admin->syncPermissions($permissions);
        }
    }



    private function createSystemOwner($permissions, $company)
    {
        $user = User::create([
            'nickname' => 'مدير النظام',
            'email' => 'admin@admin.com',
            'full_name' => 'هشام محمد',
            'username' => 'system_owner',
            'password' => bcrypt('12345678'),
            'phone' => '01006444991',
            'company_id' => $company ? $company->id : null,
        ]);
        // إخبار النظام برقم الشركة قبل منح الصلاحيات (Spatie Teams)
        if (config('permission.teams')) {
            setPermissionsTeamId($company->id);
        }
        $user->givePermissionTo($permissions);

        // مزامنة المستخدم مع جميع الشركات
        $companyIds = Company::pluck('id')->toArray();
        $pivotData = [];
        foreach ($companyIds as $companyId) {
            $pivotData[$companyId] = [
                'created_by' => $user->id,
                'nickname_in_company' => $user->nickname,
                'full_name_in_company' => $user->full_name,
                'status' => 'active',
            ];
        }
        $user->companies()->sync($pivotData);
        // إنشاء صناديق المستخدم الافتراضية لكل شركة
        // $user->ensure=CashBoxesForAllCompanies();
    }
}
