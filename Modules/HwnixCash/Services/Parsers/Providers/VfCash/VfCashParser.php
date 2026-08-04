<?php
// محول ومفكك رسائل شركة فودافون كاش الشامل والقائم على الأنماط والقواعد المعمارية v5 Lite.

namespace Modules\HwnixCash\Services\Parsers\Providers\VfCash;

use Modules\HwnixCash\Contracts\Parsers\MessagePatternInterface;
use Modules\HwnixCash\Contracts\Parsers\ProviderParserInterface;
use Modules\HwnixCash\Domain\Enums\ParserResultStatus;
use Modules\HwnixCash\Domain\Enums\TransactionType;
use Modules\HwnixCash\DTOs\NormalizedSmsContext;
use Modules\HwnixCash\DTOs\ParsedSmsResultDTO;
use Modules\HwnixCash\Services\Parsers\Factories\ParsedSmsResultFactory;
use Modules\HwnixCash\Services\Parsers\Providers\VfCash\Patterns\VfBalancePattern;
use Modules\HwnixCash\Services\Parsers\Providers\VfCash\Patterns\VfPromotionPattern;
use Modules\HwnixCash\Services\Parsers\Providers\VfCash\Patterns\VfReceivePattern;
use Modules\HwnixCash\Services\Parsers\Providers\VfCash\Patterns\VfRechargePattern;
use Modules\HwnixCash\Services\Parsers\Providers\VfCash\Patterns\VfSendPattern;
use Modules\HwnixCash\Services\Parsers\Providers\VfCash\Patterns\VfWrongPinPattern;

final class VfCashParser implements ProviderParserInterface
{
    /** @var array<MessagePatternInterface> */
    private array $patterns;

    public function __construct(array $patterns = [])
    {
        if (empty($patterns)) {
            $patterns = [
                new VfReceivePattern(),
                new VfSendPattern(),
                new VfRechargePattern(),
                new VfBalancePattern(),
                new VfWrongPinPattern(),
                new VfPromotionPattern(),
            ];
        }

        // ترتيب الأنماط: الأولوية للأعلى رقماً
        usort($patterns, function (MessagePatternInterface $a, MessagePatternInterface $b) {
            return $b->getPriority() <=> $a->getPriority();
        });

        $this->patterns = $patterns;
    }

    public function getProviderKey(): string
    {
        return 'vodafone_cash';
    }

    public function getAliases(): array
    {
        return [
            'VF-Cash',
            'VF Cash',
            'VodafoneCash',
            'Vodafone Cash',
            'VFCash',
            'vf-cash',
        ];
    }

    public function getSenderRegexPatterns(): array
    {
        return [
            '/^VF[-_\s]?Cash\d*$/i',
            '/^Vodafone[-_\s]?Cash$/i',
        ];
    }

    public function getVersion(): string
    {
        return '1.0.0';
    }

    public function isEnabled(): bool
    {
        return true;
    }

    public function getSupportedCapabilities(): array
    {
        return [
            TransactionType::RECEIVE,
            TransactionType::SEND,
            TransactionType::BALANCE,
            TransactionType::WRONG_PIN,
            TransactionType::NONE,
        ];
    }

    public function parse(NormalizedSmsContext $context): ParsedSmsResultDTO
    {
        foreach ($this->patterns as $pattern) {
            if ($pattern->matches($context)) {
                $matchResult = $pattern->extract($context);

                return ParsedSmsResultFactory::createFromMatch(
                    match: $matchResult,
                    parserName: 'VfCashParser',
                    parserVersion: $this->getVersion(),
                    providerKey: $this->getProviderKey(),
                    senderAlias: $context->normalizedSender,
                    stage: 'rule_based',
                    parsedBy: class_basename($pattern)
                );
            }
        }

        return ParsedSmsResultDTO::unsupported(
            providerKey: $this->getProviderKey(),
            parserName: 'VfCashParser',
            parserVersion: $this->getVersion()
        );
    }
}
