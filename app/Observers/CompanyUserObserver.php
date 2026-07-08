<?php

namespace App\Observers;

use App\Models\CompanyUser;
use App\Services\CashBoxService; 
use Illuminate\Support\Facades\Log; // لإضافة تسجيل للأخطاء

class CompanyUserObserver
{
    protected CashBoxService $cashBoxService;

    /**
     * حقن الخدمة (Dependency Injection)
     */
    public function __construct(CashBoxService $cashBoxService)
    {
        $this->cashBoxService = $cashBoxService;
    }

    /**
     * Handle the CompanyUser "created" event.
     */
    /**
     * Handle the CompanyUser "created" event.
     */
    public function created(CompanyUser $companyUser): void
    {
        try {
            // التحقق من نوع العلاقة في business_relations لتحديد هل المستخدم موظف
            // الدور محدد في جدول business_relations وليس في company_user
            $isExternalParty = \Modules\Companies\Models\BusinessRelation::where([
                'company_id' => $companyUser->company_id,
                'user_id'    => $companyUser->user_id,
            ])->whereIn('relation_type', ['customer', 'supplier'])
              ->exists();

            if ($isExternalParty) {
                // العميل أو المورد: لا خزنة نقدية — ذممهم في stakeholder_financial_balances
                return;
            }

            // الموظف أو المستخدم غير المصنّف: ينشئ له خزنة افتراضية
            $this->cashBoxService->createDefaultCashBoxForUserCompany(
                userId: $companyUser->user_id,
                companyId: $companyUser->company_id,
                createdById: $companyUser->created_by
            );
        } catch (\Exception $e) {
            Log::error("فشل إنشاء خزنة للمستخدم {$companyUser->user_id} والشركة {$companyUser->company_id}: " . $e->getMessage());
        }
    }

    /**
     * Handle the CompanyUser "updated" event.
     */
    public function updated(CompanyUser $companyUser): void
    {
        // تم إلغاء مزامنة الأدوار بعد حذف عمود role
    }

    /**
     * Handle the CompanyUser "deleted" event.
     */
    public function deleted(CompanyUser $companyUser): void
    {
        try {
            // تعطيل الخزنة الافتراضية للشركة التي تم فك ارتباطها.
            $this->cashBoxService->disableDefaultCashBoxForUserCompany(
                userId: $companyUser->user_id,
                companyId: $companyUser->company_id
            );

            // حذف العلاقات التجارية للطرف
            \Modules\Companies\Models\BusinessRelation::where([
                'company_id' => $companyUser->company_id,
                'user_id' => $companyUser->user_id,
            ])->delete();
        } catch (\Exception $e) {
             // تسجيل الخطأ إذا فشلت عملية تعطيل الخزنة
             Log::error("فشل تعطيل خزنة أو حذف علاقات للمستخدم {$companyUser->user_id} والشركة {$companyUser->company_id}: " . $e->getMessage());
        }
    }

    /**
     * Handle the CompanyUser "restored" event.
     */
    public function restored(CompanyUser $companyUser): void
    {
        // يمكن إضافة منطق لإعادة تنشيط الخزنة هنا إذا كنت تستخدم Soft Deletes.
    }

    /**
     * Handle the CompanyUser "force deleted" event.
     */
    public function forceDeleted(CompanyUser $companyUser): void
    {
        // يمكن إضافة منطق لحذف سجل الخزنة نهائيًا هنا إذا كان هذا هو المطلوب.
    }
}
