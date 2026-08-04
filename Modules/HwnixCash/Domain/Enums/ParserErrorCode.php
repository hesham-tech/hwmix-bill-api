<?php
// إينوم رموز أخطاء التحليل الموحدة في محرك الرسائل.

namespace Modules\HwnixCash\Domain\Enums;

enum ParserErrorCode: string
{
    case NONE = 'none';
    case PROVIDER_NOT_FOUND = 'provider_not_found';
    case PATTERN_NOT_MATCHED = 'pattern_not_matched';
    case INVALID_MESSAGE = 'invalid_message';
    case PARSER_EXCEPTION = 'parser_exception';
}
