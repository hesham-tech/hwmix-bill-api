<?php
// مزود خدمة تسجيل الأحداث والمستمعات التابعة لموديول كاش هونكس HwnixCash.

namespace Modules\HwnixCash\Providers;

use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;

class EventServiceProvider extends ServiceProvider
{
    /**
     * الأحداث والمستمعات المسجلة بالموديول.
     *
     * @var array<string, array<int, string>>
     */
    protected $listen = [];

    /**
     * تسجيل الأحداث تلقائياً عبر المزود.
     */
    public function boot(): void
    {
        parent::boot();
    }
}
