<?php
// كلاس نقل نواتج التحليل الموحد لجميع محركات التحليل بالنظام.

namespace Modules\HwnixCash\DTOs;

use Modules\HwnixCash\Domain\Enums\ParserResultStatus;

final class ParsedSmsResultDTO
{
    public function __construct(
        public readonly ParserResultStatus $status,
        public readonly bool $isSupported,
        public readonly bool $isFinancial,
        public readonly string $parserName,
        public readonly string $parserVersion,
        public readonly string $messageType,       // 'transaction', 'promotion', 'notification', 'unknown'
        public readonly string $transactionType,   // 'receive', 'send', 'withdraw', 'deposit', 'balance', 'none'
        public readonly bool $isTransaction,
        public readonly ?float $amount,
        public readonly ?float $fee = 0.0,
        public readonly ?string $currency,
        public readonly ?string $targetPhone,
        public readonly ?string $targetName,
        public readonly ?string $transactionId,
        public readonly ?string $datetime,
        public readonly bool $balanceFound,
        public readonly ?float $availableBalance,
        public readonly ParserMetadata $metadata
    ) {}

    public static function unsupported(
        string $providerKey,
        string $parserName,
        string $parserVersion
    ): self {
        return new self(
            status: ParserResultStatus::UNSUPPORTED_PROVIDER,
            isSupported: false,
            isFinancial: false,
            parserName: $parserName,
            parserVersion: $parserVersion,
            messageType: 'unknown',
            transactionType: 'none',
            isTransaction: false,
            amount: null,
            currency: 'EGP',
            targetPhone: null,
            targetName: null,
            transactionId: null,
            datetime: null,
            balanceFound: false,
            availableBalance: null,
            metadata: new ParserMetadata(
                patternId: '',
                parserStage: 'rule_based',
                providerKey: $providerKey,
                senderAlias: '',
                parsedBy: $parserName
            )
        );
    }

    public static function failed(ParserResultStatus $status): self
    {
        return new self(
            status: $status,
            isSupported: false,
            isFinancial: false,
            parserName: 'PipelineMessageParser',
            parserVersion: '1.0.0',
            messageType: 'unknown',
            transactionType: 'none',
            isTransaction: false,
            amount: null,
            currency: 'EGP',
            targetPhone: null,
            targetName: null,
            transactionId: null,
            datetime: null,
            balanceFound: false,
            availableBalance: null,
            metadata: new ParserMetadata(
                patternId: '',
                parserStage: 'pipeline_fallback',
                providerKey: 'unknown',
                senderAlias: '',
                parsedBy: 'PipelineMessageParser'
            )
        );
    }
}
