<?php
// حدث يُطلق عند تسجيل وجلب جهاز أندرويد جديد في النظام.

namespace Modules\SmsGateway\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Modules\SmsGateway\Domain\Entities\Device;

class DeviceRegistered
{
    use Dispatchable, SerializesModels;

    /**
     * Create a new event instance.
     */
    public function __construct(
        public Device $device
    ) {}
}
