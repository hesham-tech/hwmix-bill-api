<?php

namespace Modules\Legal\Providers;

use Nwidart\Modules\Support\ModuleServiceProvider;

/**
 * مقدم الخدمة الأساسي لموديول المستندات القانونية.
 */
class LegalServiceProvider extends ModuleServiceProvider
{
    /**
     * The name of the module.
     */
    protected string $name = 'Legal';

    /**
     * The lowercase version of the module name.
     */
    protected string $nameLower = 'legal';

    /**
     * Provider classes to register.
     *
     * @var string[]
     */
    protected array $providers = [
        RouteServiceProvider::class,
    ];
}
