<?php
// مصنع بناء نواتج DTO لنتائج التحليل الموحدة بشكل ثابت ومحمي.

namespace Modules\HwnixCash\Services\Parsers\Factories;

use Modules\HwnixCash\DTOs\ParsedSmsResultDTO;
use Modules\HwnixCash\DTOs\ParserMetadata;
use Modules\HwnixCash\DTOs\PatternMatchResult;
use Modules\HwnixCash\Domain\Enums\ParserResultStatus;

final class ParsedSmsResultFactory
{
    public static function createFromMatch(
        PatternMatchResult $match,
        string $parserName,
        string $parserVersion,
        string $providerKey,
        string $senderAlias,
        string $stage = 'rule_based',
        ?string $parsedBy = null
    ): ParsedSmsResultDTO {
        $metadata = new ParserMetadata(
            patternId: $match->patternId,
            parserStage: $stage,
            providerKey: $providerKey,
            senderAlias: $senderAlias,
            extra: $match->extractedMetadata,
            parsedBy: $parsedBy ?? $parserName
        );

        return new ParsedSmsResultDTO(
            status: $match->status,
            isSupported: true,
            isFinancial: $match->isFinancial,
            parserName: $parserName,
            parserVersion: $parserVersion,
            messageType: $match->messageCategory->value,
            transactionType: $match->transactionType->value,
            isTransaction: $match->isTransaction,
            amount: $match->amount,
            currency: $match->currency ?? 'EGP',
            targetPhone: $match->targetPhone,
            targetName: $match->targetName,
            transactionId: $match->transactionId,
            datetime: $match->datetime,
            balanceFound: $match->balanceFound,
            availableBalance: $match->availableBalance,
            metadata: $metadata
        );
    }
}
