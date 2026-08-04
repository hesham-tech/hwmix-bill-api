<?php
// نمط تفكيك واستخراج رسائل الاستعلام عن رصيد المحفظة لشركة فودافون كاش.

namespace Modules\HwnixCash\Services\Parsers\Providers\VfCash\Patterns;

use Modules\HwnixCash\Contracts\Parsers\MessagePatternInterface;
use Modules\HwnixCash\Domain\Enums\MessageCategory;
use Modules\HwnixCash\Domain\Enums\ParserResultStatus;
use Modules\HwnixCash\Domain\Enums\TransactionType;
use Modules\HwnixCash\DTOs\NormalizedSmsContext;
use Modules\HwnixCash\DTOs\PatternMatchResult;

final class VfBalancePattern implements MessagePatternInterface
{
    public function getPatternId(): string
    {
        return 'VF_BALANCE_001';
    }

    public function getCategory(): MessageCategory
    {
        return MessageCategory::TRANSACTION;
    }

    public function getPriority(): int
    {
        return 80;
    }

    public function matches(NormalizedSmsContext $context): bool
    {
        $body = $context->normalizedBody;

        // الرسائل التي تتكون من استعلام رصيد صافي دون تحويلات
        $hasBalance = (mb_strpos($body, 'رصيد') !== false && mb_strpos($body, 'الحالي') !== false);
        $noTransfer = (mb_strpos($body, 'تم استلام') === false && mb_strpos($body, 'تم تحويل') === false && mb_strpos($body, 'تم خصم') === false);

        return $hasBalance && $noTransfer;
    }

    public function extract(NormalizedSmsContext $context): PatternMatchResult
    {
        $body = $context->normalizedBody;

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
            transactionType: TransactionType::BALANCE,
            isTransaction: false, // استعلام رصيد وليس حركة سحب/إيداع
            amount: 0.0,
            currency: 'EGP',
            targetPhone: null,
            targetName: null,
            transactionId: null,
            datetime: $context->originalContext->receivedAt,
            balanceFound: $balanceFound,
            availableBalance: $availableBalance,
            extractedMetadata: [
                'rule_name' => 'VfBalancePattern',
                'matched_text' => $body,
            ]
        );
    }
}
