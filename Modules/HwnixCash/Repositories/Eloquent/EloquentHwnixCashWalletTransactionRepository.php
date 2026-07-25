<?php
// المستودع المادي لإدارة معاملات المحافظ الإلكترونية باستخدام Eloquent.

namespace Modules\HwnixCash\Repositories\Eloquent;

use Illuminate\Support\Collection;
use Modules\HwnixCash\Domain\Contracts\HwnixCashWalletTransactionRepositoryInterface;
use Modules\HwnixCash\Domain\Entities\WalletTransactionEntity;
use Modules\HwnixCash\Domain\Enums\WalletOperationType;
use Modules\HwnixCash\Domain\Enums\WalletProvider;
use Modules\HwnixCash\Domain\Enums\WalletTransactionSource;
use Modules\HwnixCash\Domain\Enums\WalletTransactionStatus;
use Modules\HwnixCash\DTOs\WalletTransactionData;
use Modules\HwnixCash\Models\HwnixCashWalletTransaction;

class EloquentHwnixCashWalletTransactionRepository implements HwnixCashWalletTransactionRepositoryInterface
{
    public function findById(int $id): ?WalletTransactionEntity
    {
        $model = HwnixCashWalletTransaction::find($id);
        return $model ? $this->toEntity($model) : null;
    }

    public function create(WalletTransactionData $dto, int $companyId, int $userId): WalletTransactionEntity
    {
        $transaction = HwnixCashWalletTransaction::create([
            'company_id' => $companyId,
            'created_by' => $userId,
            'line_id' => $dto->lineId,
            'operation_type' => $dto->operationType,
            'provider' => $dto->provider,
            'status' => $dto->status,
            'source' => $dto->source,
            'amount' => $dto->amount,
            'fee' => $dto->fee,
            'balance_after' => $dto->balanceAfter,
            'currency' => $dto->currency,
            'operation_number' => $dto->operationNumber,
            'operation_at' => $dto->operationAt ?? now(),
            'target_phone' => $dto->targetPhone,
            'target_name' => $dto->targetName,
            'bill_number' => $dto->billNumber,
            'raw_sms' => $dto->rawSms,
            'metadata' => $dto->metadata,
        ]);

        return $this->toEntity($transaction);
    }

    public function update(int $id, WalletTransactionData $dto): ?WalletTransactionEntity
    {
        $transaction = HwnixCashWalletTransaction::find($id);
        if (!$transaction) {
            return null;
        }

        $transaction->update([
            'line_id' => $dto->lineId,
            'operation_type' => $dto->operationType,
            'provider' => $dto->provider,
            'status' => $dto->status,
            'source' => $dto->source,
            'amount' => $dto->amount,
            'fee' => $dto->fee,
            'balance_after' => $dto->balanceAfter,
            'currency' => $dto->currency,
            'operation_number' => $dto->operationNumber,
            'operation_at' => $dto->operationAt ?? $transaction->operation_at,
            'target_phone' => $dto->targetPhone,
            'target_name' => $dto->targetName,
            'bill_number' => $dto->billNumber,
            'raw_sms' => $dto->rawSms,
            'metadata' => $dto->metadata,
        ]);

        return $this->toEntity($transaction->fresh());
    }

    public function delete(int $id): bool
    {
        return (bool) HwnixCashWalletTransaction::where('id', $id)->delete();
    }

    public function getLineTransactions(int $lineId, int $companyId): Collection
    {
        return HwnixCashWalletTransaction::where('line_id', $lineId)
            ->where('company_id', $companyId)
            ->orderBy('id', 'desc')
            ->get();
    }

    protected function toEntity(HwnixCashWalletTransaction $model): WalletTransactionEntity
    {
        return new WalletTransactionEntity(
            id: $model->id,
            companyId: $model->company_id,
            createdBy: $model->created_by,
            lineId: $model->line_id,
            operationType: $model->operation_type instanceof WalletOperationType ? $model->operation_type : WalletOperationType::from($model->operation_type),
            provider: $model->provider instanceof WalletProvider ? $model->provider : WalletProvider::from($model->provider),
            status: $model->status instanceof WalletTransactionStatus ? $model->status : WalletTransactionStatus::from($model->status),
            source: $model->source instanceof WalletTransactionSource ? $model->source : WalletTransactionSource::from($model->source),
            amount: (float) $model->amount,
            fee: (float) $model->fee,
            balanceAfter: $model->balance_after !== null ? (float) $model->balance_after : null,
            currency: $model->currency,
            operationNumber: $model->operation_number,
            operationAt: $model->operation_at?->toIso8601String(),
            targetPhone: $model->target_phone,
            targetName: $model->target_name,
            billNumber: $model->bill_number,
            rawSms: $model->raw_sms,
            metadata: $model->metadata
        );
    }
}
