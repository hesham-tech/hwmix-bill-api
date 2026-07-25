<?php
// حدث تنفيذ ردود أفعال الأوامر بالهاتف.

namespace Modules\HwnixCash\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Modules\HwnixCash\Models\HwnixCashDeviceCommand;

class CommandExecuted
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public HwnixCashDeviceCommand $command
    ) {}
}
