<?php

// المدير المركزي للمنصة — يُربط بالـ Facade
namespace Modules\AiPlatform;

use Illuminate\Contracts\Foundation\Application;
use Modules\AiPlatform\Builders\AgentBuilder;
use Modules\AiPlatform\Builders\CapabilityBuilder;
use Modules\AiPlatform\Builders\WorkflowBuilder;
use Modules\AiPlatform\Plugins\AiPlatformPluginRegistry;

class AiPlatformManager
{
    public function __construct(
        protected Application            $app,
        protected AiPlatformPluginRegistry $registry,
    ) {}

    /**
     * بدء بناء طلب Direct Capability
     */
    public function capability(string $key): CapabilityBuilder
    {
        return new CapabilityBuilder($key, $this->app);
    }

    /**
     * بدء بناء طلب Agent Chat
     */
    public function agent(string $key): AgentBuilder
    {
        return new AgentBuilder($key, $this->app);
    }

    /**
     * بدء بناء طلب Workflow
     */
    public function workflow(string $key): WorkflowBuilder
    {
        return new WorkflowBuilder($key, $this->app);
    }

    /**
     * تسجيل Tools من Plugin
     */
    public function registerTools(array $toolClasses): void
    {
        foreach ($toolClasses as $toolClass) {
            $this->registry->registerDriver($toolClass);
        }
    }

    /**
     * تسجيل Driver جديد
     */
    public function registerDriver(string $driverClass): void
    {
        $this->registry->registerDriver($driverClass);
    }

    /**
     * تسجيل Capability جديدة
     */
    public function registerCapability(string $key, string $label, string $type): void
    {
        $this->registry->registerCapability($key, $label, $type);
    }

    /**
     * تسجيل Plugin كامل
     */
    public function plugin(string $key, array $config): void
    {
        $this->registry->registerPlugin($key, $config);
    }

    /**
     * جلب الـ Plugin Registry
     */
    public function registry(): AiPlatformPluginRegistry
    {
        return $this->registry;
    }
}
