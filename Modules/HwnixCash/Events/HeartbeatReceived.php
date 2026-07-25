<?php
// حدث إشارة استقبال نبضة تشغيل للهاتف.

namespace Modules\HwnixCash\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Modules\HwnixCash\Models\HwnixCashDevice;

class HeartbeatReceived
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public HwnixCashDevice $device,
        public array $metrics
    ) {}
}
