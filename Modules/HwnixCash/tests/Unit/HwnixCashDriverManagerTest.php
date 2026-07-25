<?php
// اختبارات الوحدة الخاصة بمدير وحلال سائقي الإرسال HwnixCashDriverManager.

namespace Modules\HwnixCash\tests\Unit;

use Modules\HwnixCash\Domain\Contracts\HwnixCashTransportDriverInterface;
use Modules\HwnixCash\Drivers\AndroidAgentDriver;
use Modules\HwnixCash\Drivers\HwnixCashDriverManager;
use Tests\TestCase;

class HwnixCashDriverManagerTest extends TestCase
{
    public function test_driver_manager_resolves_android_driver(): void
    {
        $manager = new HwnixCashDriverManager($this->app);
        $driver = $manager->driver('android');

        $this->assertInstanceOf(HwnixCashTransportDriverInterface::class, $driver);
        $this->assertInstanceOf(AndroidAgentDriver::class, $driver);
    }
}
