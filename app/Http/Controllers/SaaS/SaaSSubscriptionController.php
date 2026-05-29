<?php

namespace App\Http\Controllers\SaaS;

// تعليق عربي: متحكم إدارة تفاصيل اشتراكات الشركات المستأجرة بالساس وتفعيل خيار التجديد التلقائي للشركة.

use App\Http\Controllers\Controller;
use App\Models\CompanySubscription;
use App\Services\SaaS\LimitResolver;
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
     * ترقية أو تغيير باقة الاشتراك للشركة الحالية.
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
            ]);

            $planId = (int) $request->input('plan_id');

            // التحقق من أن الباقة نشطة
            $plan = \App\Models\Plan::where('id', $planId)->where('is_active', true)->first();
            if (!$plan) {
                return api_error('الباقة المطلوبة غير متوفرة حالياً أو تم تعطيلها.', [], 422);
            }

            // التحقق مما إذا كانت هذه هي الباقة الحالية النشطة
            $currentSub = CompanySubscription::where('company_id', $companyId)
                ->whereIn('status', ['active', 'trial'])
                ->get()
                ->filter(function($sub) {
                    return $sub->isActive();
                })
                ->first();

            if ($currentSub && (int) $currentSub->plan_id === $planId) {
                return api_error('أنت مشترك بالفعل في هذه الباقة.', [], 422);
            }

            // ترقية الباقة
            \App\Services\SaaS\SubscriptionService::upgradePlan($companyId, $planId);

            // جلب مصفوفة الاستهلاك الجديدة وإرجاعها
            $matrix = LimitResolver::getSubscriptionUsageMatrix($companyId);

            return api_success($matrix, 'تم تغيير وترقية باقة الاشتراك بنجاح.');
        } catch (\Throwable $e) {
            return api_exception($e);
        }
    }
}

