<?php

namespace Modules\Sales\Observers;

use Modules\Sales\Models\Invoice;
use App\Models\CompanyUser;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use App\Jobs\UpdateDailySalesSummary;
use App\Jobs\UpdateInvoiceStatsJob;
use App\Events\InvoiceCreated;

class InvoiceObserver
{
    public function created(Invoice $invoice): void
    {
        $context = $invoice->invoiceType?->context;
        if (in_array($context, ['sales', 'services'])) {
            if ($invoice->user_id && $invoice->company_id) {
                CompanyUser::where('user_id', $invoice->user_id)
                    ->where('company_id', $invoice->company_id)
                    ->increment('sales_count');
            }
        }

        if (in_array($invoice->status, ['confirmed', 'paid', 'partially_paid'])) {

            $this->dispatchSummaryUpdate($invoice);
            $this->updateUserBalanceAfter($invoice);
        }

        $this->clearDashboardCache($invoice);
        event(new InvoiceCreated($invoice));
    }

    public function updated(Invoice $invoice): void
    {
        if ($invoice->wasChanged('status') && $invoice->status === 'canceled') {
            // تسجيل نشاط الإلغاء بشكل صريح في سجل النشاط
            $invoice->logCanceled($invoice->logLabel());

            // إرسال حدث الإلغاء للأتمتة والإشعارات
            event(new \App\Events\InvoiceCanceled($invoice));

            $this->dispatchSummaryUpdate($invoice);
            $this->clearDashboardCache($invoice);
            return;
        }

        if (
            $invoice->wasChanged('status') &&
            in_array($invoice->status, ['confirmed', 'paid', 'partially_paid'])
        ) {

            $this->dispatchSummaryUpdate($invoice);
            $this->updateUserBalanceAfter($invoice);
        } elseif ($invoice->wasChanged('net_amount') && in_array($invoice->status, ['confirmed', 'paid', 'partially_paid'])) {
            $this->dispatchSummaryUpdate($invoice);
            $this->updateUserBalanceAfter($invoice);
        }

        $this->clearDashboardCache($invoice);
    }

    public function deleting(Invoice $invoice): void
    {
        // منع الحذف المادي أو الناعم المباشر للفاتورة بعد تأكيدها لضمان نزاهة الحسابات المالية
        if (in_array($invoice->status, ['confirmed', 'paid', 'partially_paid'])) {
            throw new \Exception('لا يمكن حذف الفاتورة مباشرة من قاعدة البيانات بعد تأكيدها أو دفعها. يجب إلغاء الفاتورة أولاً لعكس الأثر المالي برمجياً ومحاسبياً.');
        }
    }

    public function deleted(Invoice $invoice): void
    {
        $context = $invoice->invoiceType?->context;
        if (in_array($context, ['sales', 'services'])) {
            if ($invoice->user_id && $invoice->company_id) {
                CompanyUser::where('user_id', $invoice->user_id)
                    ->where('company_id', $invoice->company_id)
                    ->decrement('sales_count');
            }
        }

        if (in_array($invoice->status, ['confirmed', 'paid', 'partially_paid'])) {
            $this->dispatchSummaryUpdate($invoice);
        }

        $this->clearDashboardCache($invoice);
    }

    protected function dispatchSummaryUpdate(Invoice $invoice): void
    {
        $date = $invoice->issue_date ?? $invoice->created_at;
        if ($date && $invoice->company_id) {
            DB::afterCommit(function () use ($date, $invoice) {
                UpdateDailySalesSummary::dispatch(
                    $date->toDateString(),
                    $invoice->company_id
                );
                UpdateInvoiceStatsJob::dispatch($invoice);
            });
        }
    }

    protected function updateUserBalanceAfter(Invoice $invoice): void
    {
        $user = $invoice->customer;
        if ($user) {
            DB::afterCommit(function () use ($invoice, $user) {
                $user->unsetRelation('cashBoxes');
                $invoice->updateQuietly([
                    'user_balance_after' => $user->getFinancialBalance($invoice->company_id, 'receivable')
                ]);
            });
        }
    }

    protected function clearDashboardCache(Invoice $invoice): void
    {
        if ($invoice->company_id) {
            Cache::increment("dashboard_version_{$invoice->company_id}");
        }
    }
}
