<?php

namespace Modules\AiPlatform\Tests\Feature;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * يختبر هذا الكلاس حساب التكلفة وتحديث الأرصدة والميزانيات بناءً على الاستهلاك
 */
class CostEngineTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_check_budget()
    {
        $costEngine = app(\Modules\AiPlatform\Contracts\Engines\CostEngineInterface::class);
        $hasBudget = $costEngine->checkBudget(1, null, null);
        $this->assertTrue($hasBudget);
    }
}
