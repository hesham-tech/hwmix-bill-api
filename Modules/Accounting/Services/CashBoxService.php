<?php

namespace Modules\Accounting\Services;

use Modules\Accounting\Models\CashBox;
use Modules\Accounting\Models\CashBoxType;
use App\Models\Company;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * خدمة إدارة الصناديق (CashBoxService) - موديول المحاسبة
 */
class CashBoxService
{
    /**
     * تُنشئ أو تُعيد تفعيل خزنة نقدية افتراضية للمستخدم ضمن شركة محددة.
     */
    public function createDefaultCashBoxForUserCompany(int $userId, int $companyId, int $createdById): ?CashBox
    {
        try {
            $cashBox = CashBox::where('user_id', $userId)
                ->where('company_id', $companyId)
                ->where('is_active', false)
                ->first();

            if ($cashBox) {
                $cashBox->is_active = true;
                $cashBox->is_default = true;
                $cashBox->save();
                return $cashBox;
            }

            $company = Company::find($companyId);
            $cashType = CashBoxType::where('name', 'نقدي')
                ->where(function ($q) use ($companyId) {
                    $q->where('company_id', $companyId)
                        ->orWhereNull('company_id');
                })
                ->orderBy('company_id', 'desc')
                ->first();

            if (!$cashType || !$company) {
                Log::error("CashBoxService: فشل في العثور على نوع الخزنة 'نقدي' أو الشركة.");
                return null;
            }

            return CashBox::firstOrCreate(
                [
                    'user_id' => $userId,
                    'company_id' => $companyId,
                    'cash_box_type_id' => $cashType->id,
                    'is_default' => true,
                ],
                [
                    'name' => 'الخزنة النقدية',
                    'balance' => 0,
                    'created_by' => $createdById,
                    'is_active' => true,
                    'description' => "تم إنشاؤها تلقائيًا مع ارتباط المستخدم بشركة: {$company->name}",
                    'account_number' => null,
                ]
            );

        } catch (Throwable $e) {
            Log::error("CashBoxService: فشل في إنشاء/تفعيل خزنة للمستخدم {$userId} والشركة {$companyId}: " . $e->getMessage());
            return null;
        }
    }

    /**
     * تُعطّل الخزنة النقدية الافتراضية للمستخدم عند فك ارتباطه بالشركة.
     */
    public function disableDefaultCashBoxForUserCompany(int $userId, int $companyId): bool
    {
        try {
            $cashBox = CashBox::where('user_id', $userId)
                ->where('company_id', $companyId)
                ->where('is_default', true)
                ->where('is_active', true)
                ->first();

            if ($cashBox) {
                $cashBox->is_active = false;
                $cashBox->is_default = false;
                return $cashBox->save();
            }
            return true;
        } catch (Throwable $e) {
            Log::error("CashBoxService: فشل في تعطيل خزنة للمستخدم {$userId} والشركة {$companyId}: " . $e->getMessage());
            return false;
        }
    }
}
