<?php
// واجهة سائق الإرسال والنقل لموديول كاش هونكس HwnixCash.

namespace Modules\HwnixCash\Domain\Contracts;

use Modules\HwnixCash\Domain\Entities\SmsMessage;

interface HwnixCashTransportDriverInterface
{
    public function send(SmsMessage $message): bool;

    public function getDriverName(): string;
}
