<?php

// واجهة الـ Facade الرئيسية للمنصة
namespace Modules\AiPlatform\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static \Modules\AiPlatform\Builders\CapabilityBuilder capability(string $key)
 * @method static \Modules\AiPlatform\Builders\AgentBuilder      agent(string $key)
 * @method static \Modules\AiPlatform\Builders\WorkflowBuilder   workflow(string $key)
 * @method static void registerTools(array $toolClasses)
 * @method static void registerDriver(string $driverClass)
 * @method static void registerCapability(string $key, string $label, string $type)
 * @method static void plugin(string $key, array $config)
 *
 * @see \Modules\AiPlatform\AiPlatformManager
 */
class AI extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'ai-platform';
    }
}
