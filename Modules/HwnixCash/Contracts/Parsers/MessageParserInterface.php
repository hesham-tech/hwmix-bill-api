<?php
// واجهة محرك تحليل الرسائل العام للنظام.

namespace Modules\HwnixCash\Contracts\Parsers;

use Modules\HwnixCash\DTOs\IncomingSmsContext;
use Modules\HwnixCash\DTOs\ParsedSmsResultDTO;

interface MessageParserInterface
{
    public function parse(IncomingSmsContext $context): ParsedSmsResultDTO;
}
