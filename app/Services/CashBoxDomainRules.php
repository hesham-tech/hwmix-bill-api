<?php

namespace App\Services;

use Modules\Accounting\Models\CashBox;
use App\Models\User;
use App\Enums\CashBoxStatus;
use Exception;

/**
 * التحقق من قواعد العمل الأساسية والمحافظة على سلامة البيانات (CashBox Domain Rules)
 */
class CashBoxDomainRules
{
    /**
     * التحقق من سلامة قواعد الخزنة عند الإنشاء أو التعديل
     */
    public function validateCreationOrUpdate(array $data, ?int $excludeId = null): void
    {
        $userId = $data['user_id'] ?? null;
        $companyId = $data['company_id'] ?? null;
        $accessType = $data['access_type'] ?? 'personal';

        // 1. الخزنة الشخصية يجب أن يكون لها مالك شخصي واحد فقط
        if ($accessType === 'personal' && is_null($userId)) {
            throw new Exception("الخزنة الشخصية يجب أن تكون مرتبطة بموظف محدد كأمين عهدة.");
        }

        // 2. الخزنة المشتركة لا يجب أن يكون لها مالك شخصي
        if ($accessType === 'company_shared' && !is_null($userId)) {
            throw new Exception("الخزنة المشتركة لا يمكن ربطها بموظف محدد (يجب أن يكون user_id فارغاً).");
        }

        // 3. التحقق من قدرة الموظف على امتلاك عهدة مالية
        if ($userId && $companyId) {
            $user = User::withoutGlobalScopes()->find($userId);
            if ($user && !$user->hasCapability('has_cash_custody', $companyId)) {
                throw new Exception("المستخدم المحدّد لا يمتلك الصلاحية لامتلاك عهدة مالية (has_cash_custody).");
            }
        }
    }

    public function validateOperation($cashBox, float $amount, string $operationType, int $companyId): void
    {
        // 1. الخزنة المؤرشفة أو غير النشطة لا يمكن إجراء أي عملية عليها
        if ($cashBox->status !== CashBoxStatus::ACTIVE) {
            if ($cashBox->status === CashBoxStatus::ARCHIVED) {
                throw new Exception("الخزينة المحددة مؤرشفة (Legacy) ولا يمكن إجراء عمليات مالية عليها.");
            }
            if ($cashBox->status === CashBoxStatus::INACTIVE) {
                throw new Exception("الخزينة المحددة '{$cashBox->name}' معطلة ولا يمكن إجراء عمليات عليها.");
            }
            if ($cashBox->status === CashBoxStatus::LOCKED) {
                throw new Exception("الخزينة المحددة مجمدة للتدقيق المحاسبي ولا يمكن إجراء عمليات عليها.");
            }
            throw new Exception("الخزينة المحددة ليست في حالة نشطة تسمح بإجراء العمليات المالية.");
        }

        // 2. الخزنة التابعة لشركة لا يمكن استخدامها داخل شركة أخرى
        if ($cashBox->company_id !== $companyId) {
            throw new Exception("الخزينة المحددة لا تنتمي للشركة الحالية لإتمام العملية المالية.");
        }
    }

    /**
     * التحقق من سلامة انتقال الحالات وفق آلة الحالات (State Machine Transitions)
     */
    public function validateTransition($cashBox, CashBoxStatus $newStatus): void
    {
        $oldStatus = $cashBox->status;

        if ($oldStatus === $newStatus) {
            return;
        }

        // منع الانتقال المباشر من المؤرشفة إلى النشطة إلا عبر عملية استعادة (Restore) مخصصة تفحص الشروط
        if ($oldStatus === CashBoxStatus::ARCHIVED && $newStatus === CashBoxStatus::ACTIVE) {
            throw new Exception("لا يمكن تنشيط الخزائن المؤرشفة مباشرة. يرجى استخدام عملية الاستعادة الرسمية.");
        }

        // draft لا يمكنها استقبال حوالات أو حركات إلا بعد تفعيلها
        if ($oldStatus === CashBoxStatus::DRAFT && !in_array($newStatus, [CashBoxStatus::ACTIVE, CashBoxStatus::INACTIVE])) {
            throw new Exception("المسودة يمكن تنشيطها أو تعطيلها فقط كخطوة أولى.");
        }
    }
}
