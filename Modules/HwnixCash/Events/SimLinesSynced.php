<?php
// حدث يتم إطلاقه عند اكتمال مزامنة شرائح الـ SIM للهاتف.

namespace Modules\HwnixCash\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Modules\HwnixCash\Models\HwnixCashDevice;

class SimLinesSynced
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public HwnixCashDevice $device
    ) {}
}
