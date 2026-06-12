<?php

namespace App\Http\Controllers\SaaS;

// تعليق عربي: متحكم إدارة تفاصيل اشتراكات الشركات المستأجرة بالساس وتفعيل خيار التجديد التلقائي للشركة.

use App\Http\Controllers\Controller;
use App\Models\CompanySubscription;
use App\Services\SaaS\LimitResolver;
use App\Services\SaaS\PricingCalculator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class SaaSSubscriptionController extends Controller
{
    /**
     * جلب تفاصيل اشتراك الشركة الحالية ومصفوفة استهلاك الموارد.
     */
    public function mySubscription(): JsonResponse
    {
        try {
            $companyId = Auth::user()->active_company_id;
            if (!$companyId) {
                return api_error('المستخدم غير مرتبط بشركة نشطة.', [], 400);
            }

            $matrix = LimitResolver::getSubscriptionUsageMatrix($companyId);
            
            return api_success($matrix, 'تم جلب تفاصيل الاشتراك بنجاح.');
        } catch (\Throwable $e) {
            return api_exception($e);
        }
    }

    /**
     * تفعيل أو تعطيل خيار التجديد التلقائي للاشتراك النشط.
     */
    public function toggleAutoRenew(Request $request): JsonResponse
    {
        try {
            $companyId = Auth::user()->active_company_id;
            if (!$companyId) {
                return api_error('المستخدم غير مرتبط بشركة نشطة.', [], 400);
            }

            $request->validate([
                'auto_renew' => 'required|boolean',
            ]);

            $subscription = CompanySubscription::where('company_id', $companyId)
                ->whereIn('status', ['active', 'trial'])
                ->get()
                ->filter(function($sub) {
                    return $sub->isActive();
                })
                ->first();

            if (!$subscription) {
                return api_error('لا يوجد اشتراك نشط لهذه الشركة لتعديل التجديد التلقائي له.', [], 404);
            }

            $subscription->update([
                'auto_renew' => (bool) $request->input('auto_renew')
            ]);

            return api_success([
                'auto_renew' => (bool) $subscription->auto_renew
            ], 'تم تحديث حالة التجديد التلقائي بنجاح.');
        } catch (\Throwable $e) {
            return api_exception($e);
        }
    }

    /**
     * ترقية أو تجديد باقة الاشتراك للشركة الحالية مع تحديد الأشهر والكوبون.
     */
    public function upgrade(Request $request): JsonResponse
    {
        try {
            $companyId = Auth::user()->active_company_id;
            if (!$companyId) {
                return api_error('المستخدم غير مرتبط بشركة نشطة.', [], 400);
            }

            $request->validate([
                'plan_id' => 'required|integer|exists:plans,id',
                'months' => 'nullable|integer|min:1',
                'coupon_code' => 'nullable|string|max:50',
            ]);

            $planId = (int) $request->input('plan_id');
            $months = (int) ($request->input('months') ?? 1);
            $couponCode = $request->input('coupon_code');

            // التحقق من أن الباقة نشطة
            $plan = \App\Models\Plan::where('id', $planId)->where('is_active', true)->first();
            if (!$plan) {
                return api_error('الباقة المطلوبة غير متوفرة حالياً أو تم تعطيلها.', [], 422);
            }

            // حساب السعر النهائي المطلوب دفعه بعد تطبيق الخصومات والكوبون
            $pricing = PricingCalculator::calculate($planId, $months, $couponCode);
            if (!empty($pricing['coupon_error'])) {
                return api_error($pricing['coupon_error'], [], 422);
            }

            $totalPrice = (float) $pricing['total_price'];

            // التحقق مما إذا كانت هذه هي الباقة الحالية النشطة بنفس مدتها (لتفادي تكرار التجديد دون داعي)
            $currentSub = CompanySubscription::where('company_id', $companyId)
                ->whereIn('status', ['active', 'trial'])
                ->get()
                ->filter(function($sub) {
                    return $sub->isActive();
                })
                ->first();

            // إذا كان الاشتراك الحالي نشطاً وله نفس الباقة ونفس عدد الأشهر ولم يتم تمرير كوبون جديد، فقد لا يرغب المستخدم في الترقية لنفس الباقة مجدداً إلا إذا كان تجديداً
            if ($currentSub && (int) $currentSub->plan_id === $planId && $currentSub->months === $months && !$couponCode && $totalPrice > 0) {
                // نسمح بالتجديد (تأثير التجديد يتمثل في تمديد فترة الانتهاء) ولكن ننبه المستخدم
            }

            // يتطلب دفعاً فورياً إذا كان السعر الإجمالي أكبر من 0 ولم تكن هناك أيام تجربة للباقة
            $requiresImmediatePayment = $totalPrice > 0 && (int) $plan->trial_days === 0;

            if ($requiresImmediatePayment) {
                $masterCompanyId = (int) config('app.master_company_id', 1);
                $gateway = \Modules\Payment\Models\PaymentGateway::where('company_id', $masterCompanyId)
                    ->where('is_active', true)
                    ->orderBy('is_default', 'desc')
                    ->first();

                if (!$gateway) {
                    return api_error('بوابات الدفع الإلكتروني غير مهيأة حالياً في النظام لاستقبال الاشتراكات المدفوعة.', [], 422);
                }

                // تهيئة اشتراك معلق الدفع بالخيارات الديناميكية
                $pendingSub = \App\Services\SaaS\SubscriptionService::initializePendingSubscription($companyId, $plan->id, $months, $couponCode);

                // إنشاء معاملة الدفع ورابط الدفع
                $processPaymentAction = app(\Modules\Payment\Actions\ProcessPaymentAction::class);
                
                // تحديد روابط النجاح والفشل للعودة للفرونت إند
                $frontendUrl = $request->input('redirect_url') ?? 'http://localhost:5173/app/my-subscription';
                $successUrl = $frontendUrl . '?payment_status=success&sub_id=' . $pendingSub->id;
                $cancelUrl = $frontendUrl . '?payment_status=cancel';

                $paymentResult = $processPaymentAction->handle([
                    'payment_gateway_id' => $gateway->id,
                    'payable_type' => CompanySubscription::class,
                    'payable_id' => $pendingSub->id,
                    'amount' => $totalPrice,
                    'currency' => $plan->currency ?: 'EGP',
                    'branch_id' => null,
                    'options' => [
                        'success_url' => $successUrl,
                        'cancel_url' => $cancelUrl,
                    ]
                ]);

                return api_success([
                    'requires_payment' => true,
                    'payment_url' => $paymentResult['payment_url'],
                    'transaction_id' => $paymentResult['transaction_id'],
                    'subscription_id' => $pendingSub->id
                ], 'يرجى إتمام عملية الدفع لتفعيل الباقة.');
            }

            // ترقية الباقة مباشرة إذا كانت مجانية بالكامل أو تملك فترة تجريبية مجانية
            \App\Services\SaaS\SubscriptionService::upgradePlan($companyId, $planId, $months, $couponCode, false);

            // جلب مصفوفة الاستهلاك الجديدة وإرجاعها
            $matrix = LimitResolver::getSubscriptionUsageMatrix($companyId);

            return api_success($matrix, 'تم تغيير وترقية باقة الاشتراك بنجاح.');
        } catch (\Throwable $e) {
            return api_exception($e);
        }
    }

    /**
     * جلب جميع اشتراكات الشركات (للسوبر أدمن فقط).
     */
    public function companiesSubscriptions(Request $request): JsonResponse
    {
        try {
            /** @var \App\Models\User $authUser */
            $authUser = Auth::user();
            if (!$authUser || (!$authUser->hasPermissionTo(perm_key('admin.super')) && !$authUser->hasPermissionTo(perm_key('subscriptions.view_all')))) {
                return api_forbidden('هذا الإجراء غير مصرح به.');
            }

            $masterCompanyId = (int) config('app.master_company_id', 1);
            $query = \App\Models\Company::query();

            // فلاتر البحث
            if ($request->filled('search')) {
                $search = $request->input('search');
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                      ->orWhere('email', 'like', "%{$search}%")
                      ->orWhere('phone', 'like', "%{$search}%");
                });
            }

            $companies = $query->orderBy('id', 'desc')->get();
            $result = [];

            foreach ($companies as $company) {
                // جلب الاشتراك النشط للشركة
                $subscription = CompanySubscription::where('company_id', $company->id)
                    ->whereIn('status', ['active', 'trial'])
                    ->get()
                    ->filter(function(CompanySubscription $sub) {
                        return $sub->isActive();
                    })
                    ->first();

                // حساب إحصائيات الاستهلاك للشركة
                // 1. عدد المستخدمين
                $usersCount = \App\Models\CompanyUser::where('company_id', $company->id)->count();

                // 2. عدد المنتجات
                $productsCount = \Modules\Inventory\Models\Product::withoutGlobalScopes()
                    ->where('company_id', $company->id)
                    ->count();

                // 3. عدد الفواتير
                $invoicesCount = \App\Models\Invoice::withoutGlobalScopes()
                    ->where('company_id', $company->id)
                    ->count();

                // 4. عدد المخازن
                $warehousesCount = \Modules\Inventory\Models\Warehouse::withoutGlobalScopes()
                    ->where('company_id', $company->id)
                    ->count();

                // جلب اسم مالك الشركة (أول مستخدم يملك صلاحية admin.company في هذه الشركة)
                // وإذا لم يوجد، نأتي بمنشئ الشركة
                $owner = null;
                $permission = \Illuminate\Support\Facades\DB::table('permissions')
                    ->where('name', 'admin.company')
                    ->first();
                if ($permission) {
                    $ownerId = \Illuminate\Support\Facades\DB::table('model_has_permissions')
                        ->where('permission_id', $permission->id)
                        ->where('model_type', \App\Models\User::class)
                        ->where('company_id', $company->id)
                        ->value('model_id');
                    if ($ownerId) {
                        $owner = \App\Models\User::withoutGlobalScopes()->find($ownerId);
                    }
                }
                if (!$owner && $company->created_by) {
                    $owner = \App\Models\User::withoutGlobalScopes()->find($company->created_by);
                }

                $result[] = [
                    'company_id' => $company->id,
                    'company_name' => $company->name,
                    'company_email' => $company->email,
                    'company_phone' => $company->phone,
                    'owner_name' => $owner ? $owner->full_name : 'غير محدد',
                    'owner_phone' => $owner ? $owner->phone : '',
                    'plan_id' => $subscription ? $subscription->plan_id : null,
                    'plan_name' => $subscription && $subscription->plan ? $subscription->plan->name : 'لا يوجد اشتراك',
                    'subscription_status' => $subscription ? $subscription->status : 'inactive',
                    'starts_at' => $subscription && $subscription->starts_at ? $subscription->starts_at->toDateString() : null,
                    'ends_at' => $subscription && $subscription->ends_at ? $subscription->ends_at->toDateString() : null,
                    'trial_ends_at' => $subscription && $subscription->trial_ends_at ? $subscription->trial_ends_at->toDateString() : null,
                    'is_master' => $company->id === $masterCompanyId,
                    'usage' => [
                        'users' => $usersCount,
                        'products' => $productsCount,
                        'invoices' => $invoicesCount,
                        'warehouses' => $warehousesCount,
                    ]
                ];
            }

            return api_success($result, 'تم جلب اشتراكات الشركات بنجاح.');
        } catch (\Throwable $e) {
            return api_exception($e);
        }
    }

    /**
     * تغيير باقة شركة معينة (للسوبر أدمن فقط).
     */
    public function changeCompanyPlan(Request $request): JsonResponse
    {
        try {
            /** @var \App\Models\User $authUser */
            $authUser = Auth::user();
            if (!$authUser || (!$authUser->hasPermissionTo(perm_key('admin.super')) && !$authUser->hasPermissionTo(perm_key('subscriptions.update_all')))) {
                return api_forbidden('هذا الإجراء غير مصرح به.');
            }

            $request->validate([
                'company_id' => 'required|integer|exists:companies,id',
                'plan_id' => 'required|integer|exists:plans,id',
            ]);

            $companyId = (int) $request->input('company_id');
            $planId = (int) $request->input('plan_id');

            // التحقق من أن الباقة نشطة
            $plan = \App\Models\Plan::where('id', $planId)->where('is_active', true)->first();
            if (!$plan) {
                return api_error('الباقة المطلوبة غير متوفرة حالياً أو تم تعطيلها.', [], 422);
            }

            // ترقية أو تغيير باقة الشركة مع تخطي الفترة التجريبية
            $subscription = \App\Services\SaaS\SubscriptionService::upgradePlan($companyId, $planId, 1, null, true);

            // تفعيل الاشتراك مباشرة وتجاوز الدفع المعلق وتأكيد إلغاء فترة التجربة
            $subscription->update([
                'status' => 'active',
                'trial_ends_at' => null,
            ]);

            return api_success([], 'تم تغيير باقة اشتراك الشركة بنجاح.');
        } catch (\Throwable $e) {
            return api_exception($e);
        }
    }
}

