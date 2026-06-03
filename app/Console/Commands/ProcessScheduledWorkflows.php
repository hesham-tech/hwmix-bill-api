<?php

namespace App\Console\Commands;

// تعليق عربي: أمر مجدول لتشغيل وفحص قواعد الإشعارات المجدولة (مثل فواتير الاستحقاق والمستحقات المتأخرة) لجميع الشركات وإرسال التنبيهات.

use Illuminate\Console\Command;
use Modules\Notification\Models\NotificationWorkflow;
use Modules\Notification\Services\WorkflowProcessor;
use Modules\Sales\Models\Invoice;
use Carbon\Carbon;

class ProcessScheduledWorkflows extends Command
{
    /**
     * اسم الأمر البرمجي.
     */
    protected $signature = 'notifications:process-workflows';

    /**
     * وصف الأمر البرمجي.
     */
    protected $description = 'Process all scheduled notification workflows for all companies';

    /**
     * تنفيذ الأمر البرمجي.
     */
    public function handle()
    {
        $this->info('Starting scheduled workflows processing...');

        // جلب جميع قواعد أتمتة الفواتير النشطة للنظام
        $workflows = NotificationWorkflow::where('is_active', true)
            ->whereIn('event_type', ['invoice.due_soon', 'invoice.overdue'])
            ->with('steps.template')
            ->get();

        $processor = app(WorkflowProcessor::class);

        foreach ($workflows as $workflow) {
            foreach ($workflow->steps as $step) {
                if (!$step->is_active || !$step->template) continue;

                // حساب تاريخ الاستحقاق المستهدف للفحص بناءً على الإزاحة delay_days
                // إذا كان التأخير -3 أيام (قبل الاستحقاق)، فالتاريخ المستهدف هو اليوم + 3
                // إذا كان التأخير 5 أيام (بعد الاستحقاق)، فالتاريخ المستهدف هو اليوم - 5
                $targetDate = Carbon::today()->subDays($step->delay_days)->toDateString();

                // جلب الفواتير غير المدفوعة التابعة للشركة والتي تستحق في التاريخ المحدد
                $invoices = Invoice::where('company_id', $workflow->company_id)
                    ->whereDate('due_date', $targetDate)
                    ->whereIn('payment_status', ['unpaid', 'partially_paid'])
                    ->get();

                foreach ($invoices as $invoice) {
                    $processor->executeStep($step, $invoice);
                }
            }
        }

        $this->info('Scheduled workflows processing completed.');
    }
}
