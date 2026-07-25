<?php
// حدث يُطلق عند إدخال أو كشف شريحة اتصال جديد في الجهاز.

namespace Modules\HwnixCash\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Modules\HwnixCash\Models\SmsLine;

class SimInserted
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public SmsLine $line
    ) {}
}
