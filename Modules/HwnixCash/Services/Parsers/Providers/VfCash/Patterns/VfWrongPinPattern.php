<?php
// نمط تفكيك وتحديد رسائل الخطأ في الرقم السري لشركة فودافون كاش.

namespace Modules\HwnixCash\Services\Parsers\Providers\VfCash\Patterns;

use Modules\HwnixCash\Contracts\Parsers\MessagePatternInterface;
use Modules\HwnixCash\Domain\Enums\MessageCategory;
use Modules\HwnixCash\Domain\Enums\ParserResultStatus;
use Modules\HwnixCash\Domain\Enums\TransactionType;
use Modules\HwnixCash\DTOs\NormalizedSmsContext;
use Modules\HwnixCash\DTOs\PatternMatchResult;

final class VfWrongPinPattern implements MessagePatternInterface
{
    public function getPatternId(): string
    {
        return 'VF_WRONG_PIN_001';
    }

    public function getCategory(): MessageCategory
    {
        return MessageCategory::SYSTEM;
    }

    public function getPriority(): int
    {
        return 70;
    }

    public function matches(NormalizedSmsContext $context): bool
    {
        $body = $context->normalizedBody;
        return (mb_strpos($body, 'الرقم السري') !== false && (mb_strpos($body, 'غير صحيح') !== false || mb_strpos($body, 'خطأ') !== false));
    }

    public function extract(NormalizedSmsContext $context): PatternMatchResult
    {
        return new PatternMatchResult(
            isMatched: true,
            status: ParserResultStatus::NON_FINANCIAL,
            isFinancial: false,
            patternId: $this->getPatternId(),
            messageCategory: MessageCategory::SYSTEM,
            transactionType: TransactionType::WRONG_PIN,
            isTransaction: false,
            amount: null,
            currency: 'EGP',
            targetPhone: null,
            targetName: null,
            transactionId: null,
            datetime: $context->originalContext->receivedAt,
            balanceFound: false,
            availableBalance: null,
            extractedMetadata: [
                'rule_name' => 'VfWrongPinPattern',
                'matched_text' => $context->normalizedBody,
            ]
        );
    }
}
