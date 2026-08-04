<?php
// المرحلة الأولى في السلسلة: معالجة القواعد المبرمجة للمزود المكتشف.

namespace Modules\HwnixCash\Services\Parsers\Stages;

use Modules\HwnixCash\Contracts\Parsers\ParserStageInterface;
use Modules\HwnixCash\Domain\Enums\ParserResultStatus;
use Modules\HwnixCash\DTOs\NormalizedSmsContext;
use Modules\HwnixCash\DTOs\ParsedSmsResultDTO;
use Modules\HwnixCash\Services\Parsers\ParserRegistry;

final class RuleBasedParserStage implements ParserStageInterface
{
    public function process(NormalizedSmsContext $context, ParserRegistry $registry): ParsedSmsResultDTO
    {
        $providerParser = $registry->resolve($context);

        if (!$providerParser) {
            return ParsedSmsResultDTO::failed(ParserResultStatus::UNSUPPORTED_PROVIDER);
        }

        return $providerParser->parse($context);
    }
}
