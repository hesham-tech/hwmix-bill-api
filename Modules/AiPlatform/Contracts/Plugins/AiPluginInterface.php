<?php

// عقد كل Plugin
namespace Modules\AiPlatform\Contracts\Plugins;

interface AiPluginInterface
{
    /** المفتاح الفريد */
    public function key(): string;

    /** الاسم المعروض */
    public function label(): string;

    /** الإصدار */
    public function version(): string;

    /** قائمة Tool Classes */
    public function tools(): array;

    /** قائمة Workflow Mappings ['key' => ClassName] */
    public function workflows(): array;

    /** مفاتيح Prompts المطلوبة */
    public function prompts(): array;

    /** يُستدعى عند تسجيل الـ Plugin */
    public function boot(): void;
}
