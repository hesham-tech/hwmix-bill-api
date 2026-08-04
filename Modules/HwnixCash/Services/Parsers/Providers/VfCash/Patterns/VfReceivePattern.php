<?php
// نمط تفكيك وتحديد رسائل استقبال التحويلات المالي لشركة فودافون كاش.

namespace Modules\HwnixCash\Services\Parsers\Providers\VfCash\Patterns;

use Modules\HwnixCash\Contracts\Parsers\MessagePatternInterface;
use Modules\HwnixCash\Domain\Enums\MessageCategory;
use Modules\HwnixCash\Domain\Enums\ParserResultStatus;
use Modules\HwnixCash\Domain\Enums\TransactionType;
use Modules\HwnixCash\DTOs\NormalizedSmsContext;
use Modules\HwnixCash\DTOs\PatternMatchResult;

final class VfReceivePattern implements MessagePatternInterface
{
    public function getPatternId(): string
    {
        return 'VF_RECEIVE_001';
    }

    public function getCategory(): MessageCategory
    {
        return MessageCategory::TRANSACTION;
    }

    public function getPriority(): int
    {
        return 100; // أولوية مرتفعة لرسائل التحويلات المال الواردة
    }

    public function matches(NormalizedSmsContext $context): bool
    {
        $body = $context->normalizedBody;

        // الكلمات المفتاحية الأساسية لرسالة الاستلام
        $hasReceiveKeyword = (mb_strpos($body, 'تم استلام') !== false || mb_strpos($body, 'استلمت') !== false || mb_strpos($body, 'تم إيداع') !== false);
        $hasFromKeyword = (mb_strpos($body, 'من') !== false);

        return $hasReceiveKeyword && $hasFromKeyword;
    }

    public function extract(NormalizedSmsContext $context): PatternMatchResult
    {
        $body = $context->normalizedBody;

        // 1. استخراج المبلغ (مثال: تم استلام مبلغ 500.00 ج.م أو تم استلام 500 ج.م)
        $amount = null;
        if (preg_match('/(?:استلام|إيداع)(?:\s+مبلغ)?\s+([\d\.]+)\s*(?:ج\.م|جنيه)?/u', $body, $matches)) {
            $amount = (float) $matches[1];
        }

        // 2. استخراج رقم مرسل المبلغ (مثال: من 01012345678)
        $targetPhone = null;
        if (preg_match('/من\s+(?:رقم\s+)?(\+?\d{10,14})/u', $body, $matches)) {
            $targetPhone = $matches[1];
        }

        // 3. استخراج كود أو رقم العملية (مثال: كود العملية: 105234919 أو رقم العملية 105234919)
        $transactionId = null;
        if (preg_match('/(?:كود|رقم)\s+العملية\s*:?\s*(\d+)/u', $body, $matches)) {
            $transactionId = $matches[1];
        }

        // 4. استخراج الرصيد الحالي إن وجد (مثال: رصيد حسابك الحالي 1950.50 ج.م)
        $availableBalance = null;
        $balanceFound = false;
        if (preg_match('/رصيد(?:ك| حسابك)\s+الحالي\s*:?\s*([\d\.]+)/u', $body, $matches)) {
            $availableBalance = (float) $matches[1];
            $balanceFound = true;
        }

        return new PatternMatchResult(
            isMatched: true,
            status: ParserResultStatus::SUCCESS,
            isFinancial: true,
            patternId: $this->getPatternId(),
            messageCategory: MessageCategory::TRANSACTION,
            transactionType: TransactionType::RECEIVE,
            isTransaction: true,
            amount: $amount,
            currency: 'EGP',
            targetPhone: $targetPhone,
            targetName: null,
            transactionId: $transactionId,
            datetime: $context->originalContext->receivedAt,
            balanceFound: $balanceFound,
            availableBalance: $availableBalance,
            extractedMetadata: [
                'rule_name' => 'VfReceivePattern',
                'matched_text' => $body,
            ]
        );
    }
}
