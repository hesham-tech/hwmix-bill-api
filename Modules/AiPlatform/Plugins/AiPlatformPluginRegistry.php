<?php

// سجل Plugins المُسجَّلة في المنصة
namespace Modules\AiPlatform\Plugins;

use Illuminate\Support\Collection;

class AiPlatformPluginRegistry
{
    private array $plugins = [];
    private array $tools = [];
    private array $drivers = [];
    private array $capabilities = [];

    /**
     * تسجيل Plugin كامل
     */
    public function registerPlugin(string $key, array $config): void
    {
        $this->plugins[$key] = array_merge($config, ['key' => $key]);

        // تسجيل الأدوات التابعة للـ Plugin
        foreach ($config['tools'] ?? [] as $toolClass) {
            $this->tools[$key][] = $toolClass;
        }
    }

    /**
     * تسجيل Driver جديد
     */
    public function registerDriver(string $driverClass): void
    {
        $this->drivers[] = $driverClass;
    }

    /**
     * تسجيل Capability جديدة
     */
    public function registerCapability(string $key, string $label, string $type): void
    {
        $this->capabilities[$key] = ['key' => $key, 'label' => $label, 'type' => $type];
    }

    /**
     * جلب كل Plugins
     */
    public function all(): array
    {
        return $this->plugins;
    }

    /**
     * جلب كل Tool Classes من جميع Plugins
     */
    public function allTools(): array
    {
        return array_merge(...array_values($this->tools));
    }

    /**
     * جلب كل Drivers
     */
    public function allDrivers(): array
    {
        return $this->drivers;
    }

    /**
     * جلب كل Capabilities المُسجَّلة
     */
    public function allCapabilities(): array
    {
        return $this->capabilities;
    }

    /**
     * هل Plugin مُسجَّل؟
     */
    public function has(string $key): bool
    {
        return isset($this->plugins[$key]);
    }
}
