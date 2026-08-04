<?php
// واجهة مزودي خدمات الرسائل المالية وإعلان قدراتهم ومعرفاتهم.

namespace Modules\HwnixCash\Contracts\Parsers;

use Modules\HwnixCash\DTOs\NormalizedSmsContext;
use Modules\HwnixCash\DTOs\ParsedSmsResultDTO;

interface ProviderParserInterface
{
    public function getProviderKey(): string;
    public function getAliases(): array;
    public function getSenderRegexPatterns(): array;
    public function getVersion(): string;
    public function isEnabled(): bool;
    /** @return array<\Modules\HwnixCash\Domain\Enums\TransactionType> */
    public function getSupportedCapabilities(): array;
    public function parse(NormalizedSmsContext $context): ParsedSmsResultDTO;
}
