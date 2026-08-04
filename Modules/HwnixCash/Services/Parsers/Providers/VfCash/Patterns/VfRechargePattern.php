<?php
// نمط تفكيك وتحديد رسائل شحن الرصيد لشركة فودافون كاش.

namespace Modules\HwnixCash\Services\Parsers\Providers\VfCash\Patterns;

use Modules\HwnixCash\Contracts\Parsers\MessagePatternInterface;
use Modules\HwnixCash\Domain\Enums\MessageCategory;
use Modules\HwnixCash\Domain\Enums\ParserResultStatus;
use Modules\HwnixCash\Domain\Enums\TransactionType;
use Modules\HwnixCash\DTOs\NormalizedSmsContext;
use Modules\HwnixCash\DTOs\PatternMatchResult;

final class VfRechargePattern implements MessagePatternInterface
{
    public function getPatternId(): string
    {
        return 'VF_RECHARGE_001';
    }

    public function getCategory(): MessageCategory
    {
        return MessageCategory::TRANSACTION;
    }

    public function getPriority(): int
    {
        return 95; // أولوية أعلى من VfSendPattern (90) لمطابقة شحن الرصيد أولاً
    }

    public function matches(NormalizedSmsContext $context): bool
    {
        $body = $context->normalizedBody;

        $hasRechargeKeyword = (mb_strpos($body, 'شحن رصيد') !== false || mb_strpos($body, 'تم شحن') !== false);
        $hasWalletKeyword = (mb_strpos($body, 'من محفظتك') !== false || mb_strpos($body, 'فودافون كاش') !== false);

        return $hasRechargeKeyword && $hasWalletKeyword;
    }

    public function extract(NormalizedSmsContext $context): PatternMatchResult
    {
        $body = $context->normalizedBody;

        // 1. استخراج قيمة الشحن الأصلية (مثال: تم شحن رصيد ب 10.5 ج)
        $rechargeAmount = null;
        if (preg_match('/(?:تم\s+)?شحن\s+رصيد\s+ب\s*([\d\.]+)\s*(?:ج|ج\.م|جنيه)?/u', $body, $matches)) {
            $rechargeAmount = (float) $matches[1];
        }

        // 2. استخراج المبلغ الإجمالي المخصوم من المحفظة (مثال: وخصم 15 ج من محفظتك)
        $deductedAmount = null;
        if (preg_match('/(?:وخصم|خصم)\s+([\d\.]+)\s*(?:ج|ج\.م|جنيه)?\s*من\s+محفظتك/u', $body, $matches)) {
            $deductedAmount = (float) $matches[1];
        }

        // المبلغ النهائي المؤثر على رصيد المحفظة هو المبلغ المخصوم إن وجد، وإلا قيمة الشحن
        $amount = $deductedAmount ?? $rechargeAmount;

        // حساب مصاريف الخدمة/الضريبة إن وجدت
        $serviceFee = null;
        if ($deductedAmount !== null && $rechargeAmount !== null && $deductedAmount >= $rechargeAmount) {
            $serviceFee = round($deductedAmount - $rechargeAmount, 2);
        }

        // 3. استخراج رقم المستلم الشحن (مثال: إلى رقم 01020906804)
        $targetPhone = null;
        if (preg_match('/(?:إلى|الى|إلي|لرقم)\s*(?:رقم\s+)?(\+?\d{10,14})/u', $body, $matches)) {
            $targetPhone = $matches[1];
        }

        // 4. استخراج كود أو رقم العملية إن وجد
        $transactionId = null;
        if (preg_match('/(?:كود|رقم)\s*العملية\s*:?\s*(\d+)/u', $body, $matches)) {
            $transactionId = $matches[1];
        }

        // 5. استخراج الرصيد المتبقي إن وجد (مثال: رصيد حسابك في فودافون كاش الحالي 1553.5)
        $availableBalance = null;
        $balanceFound = false;
        if (preg_match('/رصيد(?:ك| حسابك)?(?:\s+فى|\s+في)?(?:\s+فودافون\s+كاش)?\s*الحالي\s*:?\s*([\d\.]+)/u', $body, $matches)) {
            $availableBalance = (float) $matches[1];
            $balanceFound = true;
        }

        return new PatternMatchResult(
            isMatched: true,
            status: ParserResultStatus::SUCCESS,
            isFinancial: true,
            patternId: $this->getPatternId(),
            messageCategory: MessageCategory::TRANSACTION,
            transactionType: TransactionType::SEND,
            isTransaction: true,
            amount: $amount,
            currency: 'EGP',
            targetPhone: $targetPhone,
            targetName: 'شحن رصيد كارت/موبايل',
            transactionId: $transactionId,
            datetime: $context->originalContext->receivedAt,
            balanceFound: $balanceFound,
            availableBalance: $availableBalance,
            extractedMetadata: [
                'rule_name' => 'VfRechargePattern',
                'recharge_amount' => $rechargeAmount,
                'deducted_amount' => $deductedAmount,
                'service_fee' => $serviceFee,
                'matched_text' => $body,
            ]
        );
    }
}
