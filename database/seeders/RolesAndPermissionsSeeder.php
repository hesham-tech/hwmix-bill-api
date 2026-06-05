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

// تعليق عربي: سيدر لتهيئة الصلاحيات وربط المستخدم الرئيسي بجميع الصلاحيات الافتراضية للنظام دون تعديل بياناته الحالية.
class RolesAndPermissionsSeeder extends Seeder
{
    public function run()
    {
        $permissions = Permission::all();

        // نستخدم الشركة الأولى الموجودة بالفعل (التي أنشأها CompanySeeder)
        $company = Company::first();

        if (!$company) {
            $company = Company::create([
                'name' => 'شركة افتراضية للنظام',
                'email' => 'info@default-company.com',
                'phone' => '01006444991',
                'created_by' => 1,
            ]);
        }

        $admin = User::withoutGlobalScopes()->where(function ($query) {
            $query->where('email', 'admin@admin.com')
                  ->orWhere('username', 'system_owner');
        })->first();

        if (!$admin) {
            $this->createSystemOwner($permissions, $company);
        } else {
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
            'active_company_id' => $company ? $company->id : null,
        ]);
        // إخبار النظام برقم الشركة قبل منح الصلاحيات (Spatie Teams)
        if (config('permission.teams') && $company) {
            setPermissionsTeamId($company->id);
        }
        $user->givePermissionTo($permissions);

        // مزامنة المستخدم مع جميع الشركات
        $companyIds = Company::pluck('id')->toArray();
        $pivotData = [];
        $hasNickname = \Illuminate\Support\Facades\Schema::hasColumn('company_user', 'nickname_in_company');
        $hasFullName = \Illuminate\Support\Facades\Schema::hasColumn('company_user', 'full_name_in_company');

        foreach ($companyIds as $companyId) {
            $data = [
                'created_by' => $user->id,
                'status' => 'active',
            ];
            if ($hasNickname) $data['nickname_in_company'] = $user->nickname;
            if ($hasFullName) $data['full_name_in_company'] = $user->full_name;
            
            $pivotData[$companyId] = $data;
        }
        $user->companies()->sync($pivotData);
        // إنشاء صناديق المستخدم الافتراضية لكل شركة
        // $user->ensure=CashBoxesForAllCompanies();
    }
}
