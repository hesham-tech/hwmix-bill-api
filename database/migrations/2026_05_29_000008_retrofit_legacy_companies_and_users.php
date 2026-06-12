<?php

//   هجرة لربط وتفعيل باقات SaaS والانتساب للشركة الأم لجميع الشركات والمستخدمين القدامى لتهيئة بيئة SaaS بنجاح.

use Illuminate\Database\Migrations\Migration;
use App\Models\Company;
use App\Models\User;
use App\Models\Plan;
use App\Models\CompanyUser;
use App\Services\SaaS\SubscriptionService;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $masterCompanyId = (int) config('app.master_company_id', 1);

        // 1. التأكد من وجود الباقة التجريبية المجانية الافتراضية
        $freePlan = Plan::where('code', 'free_trial')->first();
        if (!$freePlan) {
            $freePlan = Plan::create([
                'name' => 'الباقة التجريبية المجانية',
                'code' => 'free_trial',
                'price' => 0.00,
                'duration' => null,
                'duration_unit' => null,
                'trial_days' => 0,
                'is_active' => true,
                'company_id' => $masterCompanyId,
                'max_users' => 3,
                'max_products' => 10,
                'max_invoices' => 5,
                'features' => [
                    'payment_gateways' => false,
                    'export_import' => false,
                    'mail_settings' => true,
                    'warehouses_multi' => false,
                    'installment_system' => false,
                    'activity_logs' => false,
                    'reports_advanced' => false,
                    'max_warehouses' => 1
                ],
                'created_by' => 1,
            ]);
        }

        // 2. تفعيل الباقة المجانية لجميع الشركات القديمة التي ليس لديها اشتراك حالي
        $companies = \DB::table('companies')->where('id', '!=', $masterCompanyId)->get();
        foreach ($companies as $company) {
            $hasSub = \App\Models\CompanySubscription::where('company_id', $company->id)
                ->whereIn('status', ['active', 'trial'])
                ->exists();

            if (!$hasSub) {
                // تفعيل الباقة المجانية تلقائياً للشركة القديمة
                SubscriptionService::initializeSubscription($company->id, $freePlan->id);
            }
        }

        // 3. تمييز وربط المستخدمين القدامى الذين يملكون شركات بالشركة الأم كعضو بسيط
        $ownerIds = [];

        // أ) جلب معرفات المستخدمين الذين يملكون صلاحية admin.company من Spatie Permissions
        $permission = \Illuminate\Support\Facades\DB::table('permissions')
            ->where('name', 'admin.company')
            ->first();

        if ($permission) {
            // 1. المستخدمون الذين لديهم الصلاحية مباشرة
            $directUserIds = \Illuminate\Support\Facades\DB::table('model_has_permissions')
                ->where('permission_id', $permission->id)
                ->where('model_type', User::class)
                ->pluck('model_id')
                ->unique()
                ->toArray();

            // 2. الأدوار التي تملك الصلاحية
            $roleIds = \Illuminate\Support\Facades\DB::table('role_has_permissions')
                ->where('permission_id', $permission->id)
                ->pluck('role_id')
                ->toArray();

            // 3. المستخدمون الذين يملكون هذه الأدوار
            $roleUserIds = [];
            if (!empty($roleIds)) {
                $roleUserIds = \Illuminate\Support\Facades\DB::table('model_has_roles')
                    ->whereIn('role_id', $roleIds)
                    ->where('model_type', User::class)
                    ->pluck('model_id')
                    ->unique()
                    ->toArray();
            }

            // دمج المعرفات بدون تكرار
            $ownerIds = array_values(array_unique(array_merge($directUserIds, $roleUserIds)));
        }

        $owners = User::whereIn('id', $ownerIds)->get();

        foreach ($owners as $owner) {
            $existsInMaster = CompanyUser::where('user_id', $owner->id)
                ->where('company_id', $masterCompanyId)
                ->exists();

            if (!$existsInMaster) {
                CompanyUser::create([
                    'user_id' => $owner->id,
                    'company_id' => $masterCompanyId,
                    'nickname_in_company' => $owner->nickname,
                    'full_name_in_company' => $owner->full_name,
                    'status' => 'active',
                    'created_by' => 1,
                ]);
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // هذه الهجرة تقوم بتحديثات بيانات تاريخية فقط ولا تحتاج لتعديل هيكلي تراجعي
    }
};
