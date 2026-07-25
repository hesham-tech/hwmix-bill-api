<?php
// حدث تحديث حالة تسليم إرسال الرسالة النصية.

namespace Modules\HwnixCash\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Modules\HwnixCash\Models\HwnixCashMessage;

class SmsStatusUpdated
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public HwnixCashMessage $message,
        public string $previousStatus
    ) {}
}
