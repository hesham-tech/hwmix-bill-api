<?php

namespace Modules\Accounting\Observers;

use Modules\Accounting\Models\Revenue;
use App\Contracts\FinancialEngineInterface;
use App\Jobs\UpdateDailySalesSummary;

/**
 * مراقب الإيرادات (RevenueObserver) - موديول المحاسبة
 * يقوم بتسجيل الحركات بالخزنة والأستاذ العام بمجرد إنشاء إيراد.
 */
class RevenueObserver
{
    public function created(Revenue $revenue): void
    {
        app(FinancialEngineInterface::class)->processRevenueCreation($revenue, []);
        $this->dispatchSummaryUpdate($revenue);
    }

    public function updated(Revenue $revenue): void
    {
        if ($revenue->wasChanged('amount') || $revenue->wasChanged('revenue_date')) {
            $this->dispatchSummaryUpdate($revenue);

            if ($revenue->wasChanged('revenue_date')) {
                $this->dispatchSummaryUpdate($revenue, $revenue->getOriginal('revenue_date'));
            }
        }
    }

    public function deleted(Revenue $revenue): void
    {
        $this->dispatchSummaryUpdate($revenue);
    }

    protected function dispatchSummaryUpdate(Revenue $revenue, $date = null): void
    {
        $date = $date ?? $revenue->revenue_date;
        if ($date && $revenue->company_id) {
            UpdateDailySalesSummary::dispatch(
                $date instanceof \Carbon\Carbon ? $date->toDateString() : (string) $date,
                $revenue->company_id
            );
        }
    }
}
