<?php
// خدمة الواجهة الموحدة لعمليات المحافظ الإلكترونية في كاش هونكس.

namespace Modules\HwnixCash\Services;

use Modules\HwnixCash\Actions\CreateWalletTransactionAction;
use Modules\HwnixCash\Domain\Entities\WalletTransactionEntity;
use Modules\HwnixCash\DTOs\WalletTransactionData;

class HwnixCashWalletService
{
    public function __construct(
        protected CreateWalletTransactionAction $createTransactionAction
    ) {}

    public function recordTransaction(WalletTransactionData $dto, int $companyId, int $userId): WalletTransactionEntity
    {
        return $this->createTransactionAction->execute($dto, $companyId, $userId);
    }
}
