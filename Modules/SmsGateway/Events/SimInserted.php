<?php
// حدث يُطلق عند التقاط وإدخال شريحة اتصال نشطة وجديدة على الهاتف.

namespace Modules\SmsGateway\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Modules\SmsGateway\Models\SmsLine;

class SimInserted
{
    use Dispatchable, SerializesModels;

    /**
     * Create a new event instance.
     */
    public function __construct(
        public SmsLine $line
    ) {}
}
