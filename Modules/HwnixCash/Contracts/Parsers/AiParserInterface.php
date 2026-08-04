<?php
// واجهة محولات الذكاء الاصطناعي لتحليل الرسائل عند الفشل في القواعد البرمجية.

namespace Modules\HwnixCash\Contracts\Parsers;

use Modules\HwnixCash\DTOs\NormalizedSmsContext;
use Modules\HwnixCash\DTOs\ParsedSmsResultDTO;

interface AiParserInterface
{
    public function analyze(NormalizedSmsContext $context): ParsedSmsResultDTO;
}
