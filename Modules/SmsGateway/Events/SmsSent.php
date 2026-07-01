<?php
// حدث يُطلق عند إرسال رسالة SMS بنجاح عبر بوابة الهاتف.

namespace Modules\SmsGateway\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class SmsSent
{
    use Dispatchable, SerializesModels;

    /**
     * Create a new event instance.
     */
    public function __construct(
        public int $messageId
    ) {}
}
