<?php
namespace Modules\Store\Listeners;
use Modules\Store\Events\SubOrderStatusChanged;
use Modules\Store\Services\StoreOutboxService;
class CreateInvoiceForDeliveredOrder {
    public function handle(SubOrderStatusChanged $event): void {
        $sub=$event->subOrder;
        if($sub->status==='delivered'&&$sub->collected_at&&!$sub->invoice_id)
            app(StoreOutboxService::class)->enqueue('invoice.create','invoice:'.$sub->id,['sub_order_id'=>$sub->id]);
    }
}
