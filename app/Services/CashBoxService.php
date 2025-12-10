<?php

namespace App\Services;

use App\Models\CashBox;
use App\Models\CashBoxType;
use App\Models\Company;
use Illuminate\Support\Facades\Log; // استخدام Log لتسجيل الأخطاء
use Throwable;

class CashBoxService
{
    /**
     * تُنشئ أو تُعيد تفعيل خزنة نقدية افتراضية للمستخدم ضمن شركة محددة.
     */
    public function createDefaultCashBoxForUserCompany(int $userId, int $companyId, int $createdById): ?CashBox
    {
        try {
            // 1. البحث عن خزنة مُعطلة سابقة لنفس المستخدم والشركة
            $cashBox = CashBox::where('user_id', $userId)
                ->where('company_id', $companyId)
                ->where('is_active', false)
                // يفضل البحث عن الخزنة التي كانت افتراضية سابقاً
                ->first();

            if ($cashBox) {
                // 2. إذا وجدت: إعادة تفعيلها وجعلها افتراضية
                $cashBox->is_active = true;
                $cashBox->is_default = true;
                $cashBox->save();
                return $cashBox;
            }

            // 3. إذا لم توجد: إنشاء خزنة جديدة
            $company = Company::find($companyId);
            $cashType = CashBoxType::where('name', 'نقدي')->first();

            if (!$cashType || !$company) {
                Log::error("CashBoxService: فشل في العثور على نوع الخزنة 'نقدي' أو الشركة {$companyId}.");
                return null;
            }

            return CashBox::firstOrCreate(
                [
                    'user_id' => $userId,
                    'company_id' => $companyId,
                    'cash_box_type_id' => $cashType->id,
                    'is_default' => true,
                    // **[جديد]** إضافة is_active للقيد
                ],
                [
                    'name' => 'الخزنة النقدية',
                    'balance' => 0,
                    'created_by' => $createdById,
                    'is_active' => true, // **[جديد]** تعيين الحقل الجديد عند الإنشاء
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
            // البحث عن الخزنة الافتراضية النشطة حالياً
            $cashBox = CashBox::where('user_id', $userId)
                ->where('company_id', $companyId)
                ->where('is_default', true) // نعتمد على أن الخزنة الافتراضية الحالية هي التي سيتم تعطيلها
                ->where('is_active', true)
                ->first();

            if ($cashBox) {
                // تعطيلها وجعلها غير افتراضية
                $cashBox->is_active = false; // **[تعديل: تعطيل الحقل الجديد]**
                $cashBox->is_default = false;
                return $cashBox->save();
            }
            return true; // إذا لم نجدها، نفترض أنها معطلة أو غير موجودة بالفعل.

        } catch (Throwable $e) {
            Log::error("CashBoxService: فشل في تعطيل خزنة للمستخدم {$userId} والشركة {$companyId}: " . $e->getMessage());
            return false;
        }
    }
}