<?php
// إجراء إنشاء وتخزين معاملة مالية جديدة للمحفظة الإلكترونية.

namespace Modules\HwnixCash\Actions;

use Modules\HwnixCash\Domain\Contracts\HwnixCashWalletTransactionRepositoryInterface;
use Modules\HwnixCash\Domain\Entities\WalletTransactionEntity;
use Modules\HwnixCash\DTOs\WalletTransactionData;

class CreateWalletTransactionAction
{
    public function __construct(
        protected HwnixCashWalletTransactionRepositoryInterface $repository
    ) {}

    public function execute(WalletTransactionData $dto, int $companyId, int $userId): WalletTransactionEntity
    {
        return $this->repository->create($dto, $companyId, $userId);
    }
}
