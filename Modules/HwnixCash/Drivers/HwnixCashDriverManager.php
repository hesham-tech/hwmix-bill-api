<?php
// مدير سائقي إرسال كاش هونكس HwnixCash.

namespace Modules\HwnixCash\Drivers;

use Illuminate\Support\Manager;
use Modules\HwnixCash\Domain\Contracts\HwnixCashTransportDriverInterface;

class HwnixCashDriverManager extends Manager
{
    public function getDefaultDriver(): string
    {
        return config('hwnixcash.default_driver', 'android');
    }

    public function createAndroidDriver(): HwnixCashTransportDriverInterface
    {
        return new AndroidAgentDriver();
    }
}
