<?php
// حدث يُطلق عند التقاط واستقبال رسالة SMS واردة بنجاح على السيرفر.

namespace Modules\SmsGateway\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Modules\SmsGateway\Domain\Entities\SmsMessage;

class SmsReceived
{
    use Dispatchable, SerializesModels;

    /**
     * Create a new event instance.
     */
    public function __construct(
        public SmsMessage $message
    ) {}
}
