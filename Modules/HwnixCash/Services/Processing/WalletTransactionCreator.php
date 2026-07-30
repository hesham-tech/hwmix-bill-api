<?php
// كلاس إنشاء المعاملات المالية للمحافظ وتحديث الرصيد الحسابي بدفتر الأستاذ.

namespace Modules\HwnixCash\Services\Processing;

use Illuminate\Support\Facades\Log;
use Modules\HwnixCash\Domain\Entities\SmsMessage;
use Modules\HwnixCash\Domain\Enums\WalletOperationType;
use Modules\HwnixCash\Domain\Enums\WalletProvider;
use Modules\HwnixCash\Domain\Enums\WalletTransactionSource;
use Modules\HwnixCash\Domain\Enums\WalletTransactionStatus;
use Modules\HwnixCash\DTOs\NormalizedFinancialSmsDTO;
use Modules\HwnixCash\Models\HwnixCashLine;
use Modules\HwnixCash\Models\HwnixCashWalletTransaction;

class WalletTransactionCreator
{
    /**
     * إنشاء معاملة مالية جديدة وتعديل الرصيد الحسابي (balance) طبقاً لنوع ونية المعاملة.
     */
    public function createTransaction(HwnixCashLine $line, SmsMessage $message, NormalizedFinancialSmsDTO $dto): ?HwnixCashWalletTransaction
    {
        $amount = $dto->amount;
        if ($amount === null || $amount <= 0) {
            return null;
        }

        $currentBookBalance = (float) $line->balance;
        $newBookBalance = $currentBookBalance;

        // طبقة القرار التجارية بالنظام (System Decision Layer)
        switch ($dto->messageType) {
            case 'wallet_receive':
            case 'wallet_deposit':
            case 'wallet_refund':
                $newBookBalance = $currentBookBalance + $amount;
                break;

            case 'wallet_send':
            case 'wallet_withdraw':
            case 'wallet_payment':
                $newBookBalance = $currentBookBalance - $amount;
                break;

            default:
                Log::info("[WalletTransactionCreator] Message type '{$dto->messageType}' is not a financial mutation. Skipping transaction creation.");
                return null;
        }

        $operationType = $this->mapToOperationType($dto->messageType);

        $currency = 'EGP';
        if (!empty($dto->currency) && strlen($dto->currency) <= 3 && preg_match('/^[A-Z]{3}$/i', $dto->currency)) {
            $currency = strtoupper($dto->currency);
        }

        $walletTx = HwnixCashWalletTransaction::create([
            'company_id' => $message->companyId,
            'created_by' => $message->createdBy,
            'line_id' => $line->id,
            'operation_type' => $operationType->value,
            'provider' => $this->detectProvider($line->carrier, $message->phoneNumber),
            'status' => WalletTransactionStatus::SUCCESS->value,
            'source' => WalletTransactionSource::SMS->value,
            'amount' => $amount,
            'fee' => 0.00,
            'balance_after' => $newBookBalance,
            'currency' => $currency,
            'operation_number' => $dto->transactionId,
            'operation_at' => !empty($dto->datetime) ? date('Y-m-d H:i:s', strtotime($dto->datetime)) : now(),
            'target_phone' => $dto->targetPhone,
            'target_name' => $dto->targetName,
            'raw_sms' => $message->messageBody,
            'metadata' => [
                'message_id' => $message->id,
                'normalized_dto' => (array) $dto,
            ],
        ]);

        // تحديث الرصيد الحسابي للخط
        $line->update(['balance' => $newBookBalance]);

        Log::info("[WalletTransactionCreator] Wallet Transaction ID {$walletTx->id} created successfully. New Book Balance: {$newBookBalance}");

        return $walletTx;
    }

    protected function mapToOperationType(string $messageType): WalletOperationType
    {
        return match ($messageType) {
            'wallet_receive' => WalletOperationType::RECEIVE,
            'wallet_send' => WalletOperationType::TRANSFER,
            'wallet_withdraw' => WalletOperationType::CASH_WITHDRAW,
            'wallet_deposit' => WalletOperationType::CASH_DEPOSIT,
            'wallet_payment' => WalletOperationType::BILL_PAYMENT,
            'wallet_refund' => WalletOperationType::REFUND,
            default => WalletOperationType::OTHER,
        };
    }

    protected function detectProvider(?string $carrier, string $phoneNumber): string
    {
        $search = strtolower(($carrier ?? '') . ' ' . $phoneNumber);
        if (str_contains($search, 'vodafone') || str_contains($search, 'voda') || str_contains($search, 'vf')) {
            return WalletProvider::VODAFONE_CASH->value;
        }
        if (str_contains($search, 'orange')) {
            return WalletProvider::ORANGE_CASH->value;
        }
        if (str_contains($search, 'etisalat') || str_contains($search, 'e&')) {
            return WalletProvider::ETISALAT_CASH->value;
        }
        if (str_contains($search, 'we')) {
            return WalletProvider::WE_CASH->value;
        }
        return WalletProvider::OTHER->value;
    }
}
