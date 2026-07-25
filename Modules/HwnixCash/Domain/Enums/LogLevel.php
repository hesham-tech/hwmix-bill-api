<?php
// تعديد مستويات تسطير السجلات والتشخيصات لأجهزة كاش هونكس.

namespace Modules\HwnixCash\Domain\Enums;

enum LogLevel: string
{
    case INFO = 'info';
    case WARNING = 'warning';
    case ERROR = 'error';
    case DEBUG = 'debug';
}
