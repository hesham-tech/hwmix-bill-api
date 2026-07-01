<?php
// حدث يُطلق عند إتمام تنفيذ أمر تشغيلي على الهاتف واستلام النتائج.

namespace Modules\SmsGateway\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Modules\SmsGateway\Models\SmsDeviceCommand;

class CommandExecuted
{
    use Dispatchable, SerializesModels;

    /**
     * Create a new event instance.
     */
    public function __construct(
        public SmsDeviceCommand $command
    ) {}
}
