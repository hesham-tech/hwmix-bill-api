<?php

namespace Modules\Notification\Listeners;

//   مستمع الأحداث (Subscriber) لتشغيل قواعد أتمتة الإشعارات الفورية لكل شركة عند حدوث أحداث معينة في النظام.

use Illuminate\Events\Dispatcher;
use Modules\Sales\Models\Invoice;
use Modules\Sales\Models\InvoicePayment;
use App\Models\User;
use Modules\Notification\Models\NotificationWorkflow;
use Modules\Notification\Services\WorkflowProcessor;
use Illuminate\Support\Facades\Log;

class NotificationWorkflowSubscriber
{
    /**
     * معالجة حدث إنشاء فاتورة جديدة.
     */
    public function handleInvoiceCreated($event)
    {
        $invoice = $event->invoice ?? $event;
        if (!$invoice instanceof \App\Models\Invoice && !$invoice instanceof \Modules\Sales\Models\Invoice)
            return;

        $this->triggerWorkflow($invoice->company_id, 'invoice.created', $invoice);
    }

    /**
     * معالجة حدث إلغاء فاتورة.
     */
    public function handleInvoiceCanceled($event)
    {
        $invoice = $event->invoice ?? $event;
        if (!$invoice instanceof \App\Models\Invoice && !$invoice instanceof \Modules\Sales\Models\Invoice)
            return;

        $this->triggerWorkflow($invoice->company_id, 'invoice.canceled', $invoice);
    }

    /**
     * معالجة حدث استلام دفعة.
     */
    public function handlePaymentReceived($event)
    {
        $payment = $event->payment ?? $event;
        if (!$payment)
            return;

        $companyId = $payment->company_id;

        $this->triggerWorkflow($companyId, 'payment.received', $payment);
    }

    /**
     * معالجة حدث إنشاء مستخدم جديد.
     */
    public function handleUserRegistered($event)
    {
        $user = $event->user ?? $event;
        if (!$user instanceof User)
            return;

        $this->triggerWorkflow($user->company_id, 'customer.created', $user);
    }

    /**
     * معالجة حدث إنشاء معاملة مالية جديدة.
     */
    public function handleTransactionCreated($event)
    {
        $transaction = $event->transaction ?? $event;
        if (!$transaction instanceof \App\Models\Transaction && !$transaction instanceof \Modules\Accounting\Models\Transaction)
            return;

        $this->triggerWorkflow($transaction->company_id, 'transaction.created', $transaction);
    }

    /**
     * معالجة حدث تحديث مهمة.
     */
    public function handleTaskUpdated($event)
    {
        $task = $event->task ?? $event;
        if (!$task instanceof \App\Models\Task)
            return;

        $this->triggerWorkflow($task->company_id, 'task.updated', $task);
    }

    /**
     * معالجة حدث إنشاء مهمة جديدة.
     */
    public function handleTaskCreated($event)
    {
        $task = $event->task ?? $event;
        if (!$task instanceof \App\Models\Task)
            return;

        $this->triggerWorkflow($task->company_id, 'task.created', $task);
    }

    /**
     * معالجة حدث إضافة منتج جديد.
     */
    public function handleProductCreated($event)
    {
        $product = $event->product ?? $event;
        if (!$product instanceof \Modules\Inventory\Models\Product)
            return;

        $this->triggerWorkflow($product->company_id, 'product.created', $product);
    }

    /**
     * معالجة حدث تحديث كمية مخزون المنتج.
     */
    public function handleStockUpdated($event)
    {
        $stock = $event->stock ?? $event;
        if (!$stock instanceof \Modules\Inventory\Models\Stock)
            return;

        $this->triggerWorkflow($stock->company_id, 'product.stock_updated', $stock);
    }

    /**
     * معالجة حدث إنشاء فاتورة للتحقق من المرتجع.
     */
    public function handleInvoiceCreatedForReturn($event)
    {
        $invoice = $event->invoice ?? $event;
        if (!$invoice instanceof \App\Models\Invoice && !$invoice instanceof \Modules\Sales\Models\Invoice)
            return;

        $type = $invoice->invoiceType;
        if ($type && $type->code === 'sale_return') {
            $this->triggerWorkflow($invoice->company_id, 'invoice.returned', $invoice);
        }
    }

    /**
     * معالجة حدث إنشاء صندوق مالي جديد.
     */
    public function handleCashBoxCreated($event)
    {
        $cashbox = $event->cashbox ?? $event;
        if (!$cashbox instanceof \App\Models\CashBox && !$cashbox instanceof \Modules\Accounting\Models\CashBox)
            return;

        $this->triggerWorkflow($cashbox->company_id, 'cashbox.created', $cashbox);
    }

    /**
     * تشغيل الأتمتة المخصصة للحدث والشركة.
     */
    protected function triggerWorkflow($companyId, string $eventType, $entity)
    {
        if (!$companyId)
            return;

        try {
            // البحث عن قاعدة الأتمتة المفعلة لهذه الشركة والحدث
            $workflow = NotificationWorkflow::where('company_id', $companyId)
                ->where('event_type', $eventType)
                ->where('is_active', true)
                ->first();

            if ($workflow) {
                // استدعاء معالج الأتمتة لتشغيل الخطوات الفورية (delay_days = 0)
                $processor = app(WorkflowProcessor::class);
                $processor->executeWorkflow($workflow, $entity, 0); // الخطوات الفورية فقط
            }
        } catch (\Throwable $e) {
            Log::error("فشل تشغيل أتمتة الإشعارات للحدث {$eventType}: " . $e->getMessage());
        }
    }

    /**
     * تسجيل المستمعين للأحداث.
     */
    public function subscribe(Dispatcher $events): void
    {
        // الاستماع لحدث إنشاء الفاتورة من موديول المبيعات
        $events->listen(
            \App\Events\InvoiceCreated::class,
            [self::class, 'handleInvoiceCreated']
        );

        // الاستماع لحدث إلغاء الفاتورة
        $events->listen(
            \App\Events\InvoiceCanceled::class,
            [self::class, 'handleInvoiceCanceled']
        );

        // الاستماع لحدث استلام الدفعة
        $events->listen(
            \App\Events\PaymentReceived::class,
            [self::class, 'handlePaymentReceived']
        );

        // الاستماع لحدث إنشاء الدفعة المبيعات كخط دفاع بديل
        $events->listen(
            'eloquent.created: Modules\Sales\Models\InvoicePayment',
            [self::class, 'handlePaymentReceived']
        );

        // الاستماع لحدث تسجيل مستخدم جديد
        $events->listen(
            'eloquent.created: App\Models\User',
            [self::class, 'handleUserRegistered']
        );

        // الاستماع لحدث إنشاء معاملة مالية جديدة
        $events->listen(
            \App\Events\TransactionCreated::class,
            [self::class, 'handleTransactionCreated']
        );

        // الاستماع لحدث تحديث المهمة
        $events->listen(
            \App\Events\TaskUpdated::class,
            [self::class, 'handleTaskUpdated']
        );

        // الاستماع لحدث إنشاء مهمة جديدة
        $events->listen(
            'eloquent.created: App\Models\Task',
            [self::class, 'handleTaskCreated']
        );

        // الاستماع لحدث إضافة منتج جديد
        $events->listen(
            'eloquent.created: Modules\Inventory\Models\Product',
            [self::class, 'handleProductCreated']
        );

        // الاستماع لحدث تحديث كمية مخزون المنتج
        $events->listen(
            'eloquent.updated: Modules\Inventory\Models\Stock',
            [self::class, 'handleStockUpdated']
        );

        // الاستماع لحدث مرتجع المبيعات عبر الفواتير المضافة حديثاً
        $events->listen(
            'eloquent.created: Modules\Sales\Models\Invoice',
            [self::class, 'handleInvoiceCreatedForReturn']
        );

        // الاستماع لحدث إنشاء صندوق مالي جديد
        $events->listen(
            'eloquent.created: App\Models\CashBox',
            [self::class, 'handleCashBoxCreated']
        );
        $events->listen(
            'eloquent.created: Modules\Accounting\Models\CashBox',
            [self::class, 'handleCashBoxCreated']
        );
    }
}
