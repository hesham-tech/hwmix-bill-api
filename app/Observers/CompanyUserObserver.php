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
            $user = $companyUser->user;
            
            // 1. التحقق مما إذا كان المستخدم يملك عهدة مالية نشطة (مثلاً موظف أو سائق)
            if ($user && $user->hasCapability(\App\Enums\CapabilityCode::HAS_CASH_CUSTODY, $companyUser->company_id)) {
                $this->cashBoxService->createDefaultCashBoxForUserCompany(
                    userId: $companyUser->user_id,
                    companyId: $companyUser->company_id,
                    createdById: $companyUser->created_by ?? $companyUser->user_id
                );
            }

            // 2. التحقق مما إذا كان المستخدم يتتبع ذمم مدينة أو دائنة
            if ($user && $user->hasCapability(\App\Enums\CapabilityCode::TRACK_RECEIVABLE, $companyUser->company_id)) {
                \Modules\Companies\Models\StakeholderFinancialBalance::firstOrCreate([
                    'company_id' => $companyUser->company_id,
                    'user_id' => $companyUser->user_id,
                    'relation_type' => 'receivable',
                ], [
                    'balance' => 0.00,
                    'created_by' => $companyUser->created_by ?? $companyUser->user_id,
                ]);
            }

            if ($user && $user->hasCapability(\App\Enums\CapabilityCode::TRACK_PAYABLE, $companyUser->company_id)) {
                \Modules\Companies\Models\StakeholderFinancialBalance::firstOrCreate([
                    'company_id' => $companyUser->company_id,
                    'user_id' => $companyUser->user_id,
                    'relation_type' => 'payable',
                ], [
                    'balance' => 0.00,
                    'created_by' => $companyUser->created_by ?? $companyUser->user_id,
                ]);
            }
        } catch (\Exception $e) {
            Log::error("فشل إنشاء خزنة أو رصيد مالي للمستخدم {$companyUser->user_id} والشركة {$companyUser->company_id}: " . $e->getMessage());
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
