<?php
// حدث يُطلق عند فشل إرسال رسالة SMS وتخزين تفاصيل الخطأ.

namespace Modules\SmsGateway\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class SmsFailed
{
    use Dispatchable, SerializesModels;

    /**
     * Create a new event instance.
     */
    public function __construct(
        public int $messageId,
        public ?string $reason = null
    ) {}
}
