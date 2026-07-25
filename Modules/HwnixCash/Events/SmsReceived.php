<?php
// حدث إشارة استلام رسالة نصية جديدة من العميل عبر كاش هونكس.

namespace Modules\HwnixCash\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Modules\HwnixCash\Models\HwnixCashMessage;

class SmsReceived
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public HwnixCashMessage $message
    ) {}
}
