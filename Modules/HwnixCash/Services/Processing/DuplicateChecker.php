<?php
// كلاس فحص منع تكرار معالجة الرسائل والمعاملات المالية بنفس المعرفات عبر واجهة المستودع.

namespace Modules\HwnixCash\Services\Processing;

use Modules\HwnixCash\Domain\Contracts\HwnixCashWalletTransactionRepositoryInterface;

class DuplicateChecker
{
    public function __construct(
        protected HwnixCashWalletTransactionRepositoryInterface $transactionRepo
    ) {}

    /**
     * فحص ما إذا كانت المعاملة قد تمت معالجتها وتسجيلها سابقاً لمنع التكرار (Idempotency Check).
     */
    public function isDuplicateTransaction(int $companyId, int $financialAccountId, ?string $operationNumber, int $messageId): bool
    {
        // 1. الفحص بواسطة رقم العملية المالية الفريد إن وجد عبر المستودع
        if ($operationNumber !== null && $operationNumber !== '') {
            if ($this->transactionRepo->existsByOperationNumber($companyId, $financialAccountId, $operationNumber)) {
                return true;
            }
        }

        // 2. الفحص بواسطة معرف الرسالة الأصلية عبر المستودع
        return $this->transactionRepo->existsByMessageId($companyId, $financialAccountId, $messageId);
    }
}
