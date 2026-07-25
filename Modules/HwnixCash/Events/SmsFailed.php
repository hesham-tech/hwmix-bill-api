<?php
// حدث يُطلق عند فشل إرسال رسالة SMS وتخزين سبب الفشل.

namespace Modules\HwnixCash\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class SmsFailed
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public int $messageId,
        public ?string $reason = null
    ) {}
}
