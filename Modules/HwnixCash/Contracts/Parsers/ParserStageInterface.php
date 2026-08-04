<?php
// واجهة مراحل سلسلة المعالجة داخل محرك الرسائل.

namespace Modules\HwnixCash\Contracts\Parsers;

use Modules\HwnixCash\DTOs\NormalizedSmsContext;
use Modules\HwnixCash\DTOs\ParsedSmsResultDTO;
use Modules\HwnixCash\Services\Parsers\ParserRegistry;

interface ParserStageInterface
{
    public function process(NormalizedSmsContext $context, ParserRegistry $registry): ParsedSmsResultDTO;
}
