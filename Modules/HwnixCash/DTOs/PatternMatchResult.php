<?php
// كلاس نقل نتائج مطابقة وتفكيك أنماط الرسائل الفردية.

namespace Modules\HwnixCash\DTOs;

use Modules\HwnixCash\Domain\Enums\MessageCategory;
use Modules\HwnixCash\Domain\Enums\ParserResultStatus;
use Modules\HwnixCash\Domain\Enums\TransactionType;

final class PatternMatchResult
{
    public function __construct(
        public readonly bool $isMatched,
        public readonly ParserResultStatus $status,
        public readonly bool $isFinancial,
        public readonly string $patternId,
        public readonly MessageCategory $messageCategory,
        public readonly TransactionType $transactionType,
        public readonly bool $isTransaction,
        public readonly ?float $amount = null,
        public readonly ?float $fee = 0.0,
        public readonly ?string $currency = 'EGP',
        public readonly ?string $targetPhone = null,
        public readonly ?string $targetName = null,
        public readonly ?string $transactionId = null,
        public readonly ?string $datetime = null,
        public readonly bool $balanceFound = false,
        public readonly ?float $availableBalance = null,
        public readonly array $extractedMetadata = []
    ) {}

    public static function notMatched(): self
    {
        return new self(
            isMatched: false,
            status: ParserResultStatus::UNKNOWN_PATTERN,
            isFinancial: false,
            patternId: '',
            messageCategory: MessageCategory::SYSTEM,
            transactionType: TransactionType::NONE,
            isTransaction: false
        );
    }
}
