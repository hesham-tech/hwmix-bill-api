<?php

/**
 * كلاس مسؤول عن تجهيز مستأجر جديد (SaaS Provisioning)
 * يقوم بإنشاء الشركة، الفرع، المالك، والإعدادات الافتراضية
 */

namespace App\Actions\Company;

use App\Models\User;
use App\Models\Company;
use App\Models\Branch;
use App\Models\Warehouse;
use App\Models\CompanyUser;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Throwable;

class ProvisionNewCompanyAction
{
    /**
     * تنفيذ عملية التجهيز
     *
     * @param array $data بيانات الشركة والمالك
     * @return array يحتوي على الشركة والمالك
     * @throws Throwable
     */
    public function execute(array $data): array
    {
        return DB::transaction(function () use ($data) {
            // 1. إنشاء الشركة
            $company = Company::create([
                'name' => $data['company_name'],
                'email' => $data['company_email'] ?? ($data['email'] ?? null),
                'phone' => $data['company_phone'] ?? ($data['phone'] ?? null),
                'address' => $data['address'] ?? null,
                'status' => 'active',
            ]);

            // 2. إنشاء المستخدم المالك (أو البحث عنه إذا كان موجوداً)
            $user = User::withoutGlobalScopes()->where('phone', $data['phone'])->first();
            
            if (!$user) {
                $user = User::create([
                    'phone' => $data['phone'],
                    'email' => $data['email'] ?? null,
                    'full_name' => $data['full_name'],
                    'nickname' => $data['nickname'] ?? $data['full_name'],
                    'password' => Hash::make($data['password']),
                    'username' => $data['username'] ?? $data['phone'],
                    'company_id' => $company->id, // الشركة الأساسية للمالك
                ]);
            }

            // 3. إنشاء الفرع الرئيسي الافتراضي
            $branch = Branch::create([
                'company_id' => $company->id,
                'name' => 'الفرع الرئيسي',
                'is_default' => true,
                'status' => 'active',
                'created_by' => $user->id,
            ]);

            // 4. ربط المالك بالشركة (Membership)
            $companyUser = CompanyUser::create([
                'user_id' => $user->id,
                'company_id' => $company->id,
                'nickname_in_company' => $user->nickname,
                'full_name_in_company' => $user->full_name,
                'status' => 'active',
                'created_by' => $user->id,
            ]);

            // 5. إسناد دور المالك (Owner) - في سياق الشركة الجديدة
            setPermissionsTeamId($company->id);
            try {
                // قد يكون الدور اسمه 'admin.company' أو 'owner' بناءً على السيستم
                $user->assignRole('admin.company'); 
            } catch (Throwable $e) {
                \Log::warning('Role admin.company not found during provisioning for company: ' . $company->id);
            }

            // 6. تهيئة المخزن الافتراضي
            Warehouse::create([
                'company_id' => $company->id,
                'branch_id' => $branch->id,
                'name' => 'المخزن الرئيسي',
                'is_default' => true,
                'created_by' => $user->id,
            ]);

            // 7. ربط المالك بالفرع
            $user->branches()->sync([$branch->id]);

            return [
                'company' => $company,
                'user' => $user,
                'branch' => $branch
            ];
        });
    }
}
