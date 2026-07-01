<?php
// حالة شريحة الاتصال الفعالة داخل منافذ الهاتف.

namespace Modules\SmsGateway\Domain\Enums;

enum LineStatus: string
{
    case Active = 'active';
    case Disabled = 'disabled';
    case NoSignal = 'no_signal';
}
