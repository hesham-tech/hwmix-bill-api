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

        $existingCompany = Company::where('email', 'company@admin.com')->first();
        if (!$existingCompany) {
            $this->createSystemCompany();
        }

        $admin = User::where('email', 'admin@admin.com')->first();
        if (!$admin) {
            $this->createSystemOwner($permissions);
        } else {
            // إخبار النظام برقم الشركة قبل مزامنة الصلاحيات (Spatie Teams)
            if (config('permission.teams')) {
                $company = Company::first();
                if ($company) {
                    setPermissionsTeamId($company->id);
                }
            }
            $admin->syncPermissions($permissions);
        }
    }


    private function createSystemCompany()
    {
        Company::firstOrCreate(
            ['email' => 'company@admin.com'],
            [
                'name' => 'System Company',
                'description' => 'A description for the system company.',
                'field' => 'Technology',
                'owner_name' => 'System Owner',
                'address' => '123 System Street',
                'phone' => '010123456789',
            ]
        );
    }

    private function createSystemOwner($permissions)
    {
        $company = Company::first();
        $user = User::create([
            'nickname' => 'System Owner',
            'email' => 'admin@admin.com',
            'full_name' => 'Admin',
            'username' => 'system_owner',
            'password' => bcrypt('12345678'),
            'phone' => '1234567890',
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
            $pivotData[$companyId] = ['created_by' => $user->id];
        }
        $user->companies()->sync($pivotData);
        // إنشاء صناديق المستخدم الافتراضية لكل شركة
        // $user->ensure=CashBoxesForAllCompanies();
    }
}
