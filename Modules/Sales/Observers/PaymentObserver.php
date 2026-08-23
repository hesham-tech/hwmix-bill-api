<?php

namespace Modules\Sales\Observers;

use Modules\Sales\Models\InvoicePayment;
use App\Models\ActivityLog;
use App\Services\NotificationService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Modules\Sales\Models\Invoice;

// مراقب عمليات دفع الفواتير لموديول المبيعات لتسجيل الأنشطة وتعديل الأرصدة المالية
class PaymentObserver
{
    public function created(InvoicePayment $payment): void
    {
        ActivityLog::create([
            'action' => 'paid',
            'description' => "تم تسجيل دفعة مبلغ " . number_format((float) $payment->amount, 2) . " للفاتورة #" . ($payment->invoice?->invoice_number ?? $payment->invoice_id),
            'model' => Invoice::class,
            'row_id' => $payment->invoice_id,
            'user_id' => Auth::id() ?? $payment->created_by,
            'company_id' => $payment->company_id,
            'branch_id' => $payment->invoice?->branch_id ?? config('app.active_branch_id') ?? Auth::user()?->branch_id,
        ]);

        app(NotificationService::class)->notifyPaymentReceived($payment);
        $this->clearDashboardCache($payment);
    }

    protected function clearDashboardCache(InvoicePayment $payment): void
    {
        if ($payment->company_id) {
            Cache::increment("dashboard_version_{$payment->company_id}");
        }
    }
}
