<?php

/**
 * كلاس مسؤول عن تجهيز مستأجر جديد (SaaS Provisioning)
 * يقوم بإنشاء الشركة، الفرع، المالك، والإعدادات الافتراضية
 */

namespace App\Actions\Company;

use App\Models\User;
use App\Models\Company;
use Modules\Companies\Models\Branch;
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
            // 1. إنشاء المستخدم المالك (أو البحث عنه إذا كان موجوداً)
            // ننشئ المستخدم أولاً لنحصل على الـ ID الخاص به ونمرره للشركة
            $user = User::withoutGlobalScopes()->where('phone', $data['phone'])->first();
            
            if (!$user) {
                $user = User::create([
                    'phone' => $data['phone'],
                    'email' => $data['email'] ?? null,
                    'full_name' => $data['full_name'],
                    'nickname' => $data['nickname'] ?? $data['full_name'],
                    'password' => Hash::make($data['password']),
                    'username' => $data['username'] ?? $data['phone'],
                ]);
            }

            // 2. إنشاء الشركة وإسناد المنشئ
            // هذا سيؤدي لتشغيل CompanyObserver الذي ينشئ (الفرع، المخزن، طرق الدفع، الخزنة) تلقائياً
            $company = Company::create([
                'name' => $data['company_name'],
                'email' => $data['company_email'] ?? ($data['email'] ?? null),
                'phone' => $data['company_phone'] ?? ($data['phone'] ?? null),
                'address' => $data['address'] ?? null,
                'status' => 'active',
                'created_by' => $user->id, // مهم جداً للـ Observer
            ]);

            // 3. تحديث المستخدم بالشركة النشطة
            $user->update(['active_company_id' => $company->id]);

            // 4. ربط المالك بالشركة (Membership)
            $companyUser = CompanyUser::create([
                'user_id' => $user->id,
                'company_id' => $company->id,
                'nickname_in_company' => $user->nickname,
                'full_name_in_company' => $user->full_name,
                'status' => 'active',
                'created_by' => $user->id,
            ]);

            // 4.1 ربط المستخدم بالشركة الأم (Master Company) كعضو بسيط بدون أدوار أو صلاحيات
            $masterCompanyId = (int) config('app.master_company_id', 1);
            if ($company->id !== $masterCompanyId) {
                CompanyUser::create([
                    'user_id' => $user->id,
                    'company_id' => $masterCompanyId,
                    'nickname_in_company' => $user->nickname,
                    'full_name_in_company' => $user->full_name,
                    'status' => 'active',
                    'created_by' => $user->id,
                ]);
            }

            // 4.2 تفعيل الباقة المحددة أو الافتراضية للشركة الجديدة
            $planId = $data['plan_id'] ?? null;
            $selectedPlan = null;
            if ($planId) {
                $selectedPlan = \App\Models\Plan::where('id', $planId)->first();
            }
            if (!$selectedPlan) {
                $selectedPlan = \App\Models\Plan::where('code', 'free_trial')->first();
            }

            if ($selectedPlan) {
                $originalUser = auth()->user();
                auth()->setUser($user);
                
                $months = isset($data['months']) ? (int) $data['months'] : 1;
                $couponCode = $data['coupon_code'] ?? null;
                
                \App\Services\SaaS\SubscriptionService::initializeSubscription($company->id, $selectedPlan->id, $months, $couponCode);
                
                if ($originalUser) {
                    auth()->setUser($originalUser);
                } else {
                    auth()->logout();
                }
            }

            // 5. إسناد صلاحية مدير الشركة (admin.company) مباشرة
            setPermissionsTeamId($company->id);
            try {
                // إسناد الصلاحية مباشرة للمستخدم في سياق هذه الشركة
                $user->givePermissionTo('admin.company');
                
                \Log::info("Provisioning: تم إسناد صلاحية 'admin.company' مباشرة للمستخدم {$user->id} في الشركة {$company->id}");
            } catch (Throwable $e) {
                \Log::error('Direct permission assignment failed during provisioning: ' . $e->getMessage());
            }

            // 6. ربط المالك بالفرع الرئيسي الذي أنشأه الـ Observer
            $branch = $company->branches()->where('is_default', true)->first();
            if ($branch) {
                $user->branches()->sync([$branch->id]);
            }

            return [
                'company' => $company,
                'user' => $user,
                'branch' => $branch
            ];
        });
    }
}
