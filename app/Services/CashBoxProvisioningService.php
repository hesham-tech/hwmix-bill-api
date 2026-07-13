<?php

namespace App\Services;

use App\Models\CashBox;
use App\Models\CashBoxType;
use App\Models\Company;
use App\Models\User;
use App\Enums\CashBoxStatus;
use App\Services\CashBoxLifecycleService;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * خدمة تزويد وسحب عهد الخزن تلقائياً للموظفين بجدول الصلاحيات (CashBox Provisioning Service)
 */
class CashBoxProvisioningService
{
    protected CashBoxLifecycleService $lifecycle;

    public function __construct(CashBoxLifecycleService $lifecycle)
    {
        $this->lifecycle = $lifecycle;
    }

    /**
     * تزويد الخزنة النقدية الافتراضية للموظف تلقائياً
     */
    public function provisionDefaultCustody(int $userId, int $companyId, int $createdById, ?int $branchId = null)
    {
        try {
            $user = User::withoutGlobalScopes()->find($userId);
            if (!$user || !$user->hasCapability('has_cash_custody', $companyId)) {
                return null;
            }

            // تحديد الفرع المستهدف
            $targetBranchId = $branchId;
            if (!$targetBranchId) {
                $defaultBranch = \Modules\Companies\Models\Branch::withoutGlobalScopes()
                    ->where('company_id', $companyId)
                    ->where('is_default', true)
                    ->first();
                $targetBranchId = $defaultBranch ? $defaultBranch->id : null;
            }

            // 1. البحث عن خزنة مُعطلة سابقة لنفس المستخدم والشركة لتفادي تكرار الإنشاء
            $cashBox = CashBox::withoutGlobalScopes()
                ->where('user_id', $userId)
                ->where('company_id', $companyId)
                ->where('status', CashBoxStatus::INACTIVE)
                ->first();

            if ($cashBox) {
                // 2. إعادة تفعيلها
                $this->lifecycle->activate($cashBox);
                
                if ($targetBranchId && is_null($cashBox->branch_id)) {
                    $cashBox->branch_id = $targetBranchId;
                    $cashBox->save();
                }
                
                $this->lifecycle->changeDefault($user, $cashBox->id);
                return $cashBox;
            }

            // 3. إنشاء خزنة جديدة
            $company = Company::find($companyId);
            $cashType = CashBoxType::where('name', 'نقدي')
                ->where(function ($q) use ($companyId) {
                    $q->where('company_id', $companyId)
                        ->orWhereNull('company_id');
                })
                ->orderBy('company_id', 'desc')
                ->first();

            if (!$cashType || !$company) {
                Log::error("CashBoxProvisioningService: فشل العثور على نوع الخزنة 'نقدي' أو الشركة.");
                return null;
            }

            // إنشاء عبر Lifecycle Service لضمان التوليد التلقائي لرمز Identity وعرض الأحداث
            $cashBox = $this->lifecycle->create([
                'name' => 'الخزنة النقدية',
                'balance' => 0,
                'created_by' => $createdById,
                'user_id' => $userId,
                'company_id' => $companyId,
                'cash_box_type_id' => $cashType->id,
                'status' => CashBoxStatus::ACTIVE->value,
                'access_type' => 'personal',
                'description' => "تم إنشاؤها تلقائيًا بموجب صلاحية العهدة للمستخدم بشركة: {$company->name}",
                'branch_id' => $targetBranchId,
            ]);

            // ربطها كافتراضية للمستخدم
            $this->lifecycle->changeDefault($user, $cashBox->id);

            return $cashBox;

        } catch (Throwable $e) {
            Log::error("CashBoxProvisioningService: فشل في تزويد عهدة الخزنة للمستخدم {$userId}: " . $e->getMessage());
            return null;
        }
    }

    /**
     * سحب وتعطيل عهدة الخزنة النقدية تلقائياً
     */
    public function deprovisionCustody(int $userId, int $companyId): bool
    {
        try {
            $user = User::withoutGlobalScopes()->find($userId);
            if (!$user) {
                return false;
            }

            // البحث عن خزن المستخدم الشخصية النشطة بالشركة
            $cashBox = CashBox::withoutGlobalScopes()
                ->where('user_id', $userId)
                ->where('company_id', $companyId)
                ->where('status', CashBoxStatus::ACTIVE)
                ->first();

            if ($cashBox) {
                $this->lifecycle->deactivate($cashBox);
            }
            return true;

        } catch (Throwable $e) {
            Log::error("CashBoxProvisioningService: فشل في سحب عهدة الخزنة للمستخدم {$userId}: " . $e->getMessage());
            return false;
        }
    }
}
