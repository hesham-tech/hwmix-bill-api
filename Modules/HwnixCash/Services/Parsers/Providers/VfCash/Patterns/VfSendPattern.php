<?php
// نمط تفكيك وتحديد رسائل إرسال التحويلات والخصم المالي لشركة فودافون كاش.

namespace Modules\HwnixCash\Services\Parsers\Providers\VfCash\Patterns;

use Modules\HwnixCash\Contracts\Parsers\MessagePatternInterface;
use Modules\HwnixCash\Domain\Enums\MessageCategory;
use Modules\HwnixCash\Domain\Enums\ParserResultStatus;
use Modules\HwnixCash\Domain\Enums\TransactionType;
use Modules\HwnixCash\DTOs\NormalizedSmsContext;
use Modules\HwnixCash\DTOs\PatternMatchResult;

final class VfSendPattern implements MessagePatternInterface
{
    public function getPatternId(): string
    {
        return 'VF_SEND_001';
    }

    public function getCategory(): MessageCategory
    {
        return MessageCategory::TRANSACTION;
    }

    public function getPriority(): int
    {
        return 90; // أولوية مرتفعة لرسائل الخصم والإرسال
    }

    public function matches(NormalizedSmsContext $context): bool
    {
        $body = $context->normalizedBody;

        $hasSendKeyword = (mb_strpos($body, 'تم تحويل') !== false || mb_strpos($body, 'تم خصم') !== false || mb_strpos($body, 'قمت بتحويل') !== false);
        $hasToKeyword = (mb_strpos($body, 'لـ') !== false || mb_strpos($body, 'إلى') !== false || mb_strpos($body, 'الى') !== false);

        return $hasSendKeyword && $hasToKeyword;
    }

    public function extract(NormalizedSmsContext $context): PatternMatchResult
    {
        $body = $context->normalizedBody;

        // 1. استخراج المبلغ المحول (مثال: تم تحويل 500.00 ج.م)
        $amount = null;
        if (preg_match('/(?:تحويل|خصم)(?:\s+مبلغ)?\s+([\d\.]+)\s*(?:ج\.م|جنيه)?/u', $body, $matches)) {
            $amount = (float) $matches[1];
        }

        // 2. استخراج رقم المستلم (مثال: لـ 01012345678 أو إلى 01012345678)
        $targetPhone = null;
        if (preg_match('/(?:لـ|إلى|الى)\s+(?:رقم\s+)?(\+?\d{10,14})/u', $body, $matches)) {
            $targetPhone = $matches[1];
        }

        // 3. استخراج كود العملية (مثال: كود العملية: 105234918)
        $transactionId = null;
        if (preg_match('/(?:كود|رقم)\s+العملية\s*:?\s*(\d+)/u', $body, $matches)) {
            $transactionId = $matches[1];
        }

        // 4. استخراج الرصيد المتبقي إن وجد (مثال: رصيد حسابك الحالي 1450.50 ج.م)
        $availableBalance = null;
        $balanceFound = false;
        if (preg_match('/رصيد(?:ك| حسابك)\s+الحالي\s*:?\s*([\d\.]+)/u', $body, $matches)) {
            $availableBalance = (float) $matches[1];
            $balanceFound = true;
        }

        // 5. استخراج مصاريف الخدمة إن وجدت
        $serviceFee = null;
        if (preg_match('/مصاريف\s+الخدمة\s*:?\s*([\d\.]+)/u', $body, $matches)) {
            $serviceFee = (float) $matches[1];
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
            targetName: null,
            transactionId: $transactionId,
            datetime: $context->originalContext->receivedAt,
            balanceFound: $balanceFound,
            availableBalance: $availableBalance,
            extractedMetadata: [
                'rule_name' => 'VfSendPattern',
                'service_fee' => $serviceFee,
                'matched_text' => $body,
            ]
        );
    }
}
