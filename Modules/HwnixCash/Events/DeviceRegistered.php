<?php
// حدث يتم إطلاقه عند تسجيل جهاز كاش هونكس جديد.

namespace Modules\HwnixCash\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Modules\HwnixCash\Models\HwnixCashDevice;

class DeviceRegistered
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public HwnixCashDevice $device
    ) {}
}
