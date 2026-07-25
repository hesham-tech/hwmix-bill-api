<?php
// حدث يُطلق عند إرسال رسالة SMS بنجاح عبر بوابة الهاتف.

namespace Modules\HwnixCash\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class SmsSent
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public int $messageId
    ) {}
}
