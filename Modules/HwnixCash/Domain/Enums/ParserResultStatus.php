<?php
// إينوم الحالات الرسمية لنتائج محرك تحليل الرسائل المالية.

namespace Modules\HwnixCash\Domain\Enums;

enum ParserResultStatus: string
{
    case SUCCESS = 'success';
    case PROMOTION = 'promotion';
    case NON_FINANCIAL = 'non_financial';
    case UNSUPPORTED_PROVIDER = 'unsupported_provider';
    case UNKNOWN_PATTERN = 'unknown_pattern';
    case INVALID_MESSAGE = 'invalid_message';
    case ERROR = 'error';
}
