<?php
// مستويات تسجيل الأخطاء والنبضات للتحكم بنوعية التفاصيل المرفوعة.

namespace Modules\SmsGateway\Domain\Enums;

enum LogLevel: string
{
    case Debug = 'debug';
    case Info = 'info';
    case Warn = 'warn';
    case Error = 'error';
}
