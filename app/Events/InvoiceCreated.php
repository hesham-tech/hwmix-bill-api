<?php

namespace App\Events;

use Modules\Sales\Models\Invoice;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * حدث يتم إطلاقه عند إنشاء فاتورة جديدة.
 */
class InvoiceCreated
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public \App\Models\Invoice|\Modules\Sales\Models\Invoice $invoice;

    /**
     * Create a new event instance.
     */
    public function __construct(\App\Models\Invoice|\Modules\Sales\Models\Invoice $invoice)
    {
        $this->invoice = $invoice;
    }
}
