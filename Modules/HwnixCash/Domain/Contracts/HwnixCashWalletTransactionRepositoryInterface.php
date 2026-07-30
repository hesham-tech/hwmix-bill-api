<?php
// واجهة مستودع بيانات معاملات المحافظ الإلكترونية لكاش هونكس.

namespace Modules\HwnixCash\Domain\Contracts;

use Illuminate\Support\Collection;
use Modules\HwnixCash\Domain\Entities\WalletTransactionEntity;
use Modules\HwnixCash\DTOs\WalletTransactionData;

interface HwnixCashWalletTransactionRepositoryInterface
{
    public function findById(int $id): ?WalletTransactionEntity;

    public function create(WalletTransactionData $dto, int $companyId, int $userId): WalletTransactionEntity;

    public function update(int $id, WalletTransactionData $dto): ?WalletTransactionEntity;

    public function delete(int $id): bool;

    public function getLineTransactions(int $lineId, int $companyId): Collection;

    public function existsByOperationNumber(int $companyId, int $financialAccountId, string $operationNumber): bool;

    public function existsByMessageId(int $companyId, int $financialAccountId, int $messageId): bool;
}
