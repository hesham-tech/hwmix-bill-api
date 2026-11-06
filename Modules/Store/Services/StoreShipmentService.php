<?php
namespace Modules\Store\Services;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Store\Models\{StoreShipment,StoreSubOrder,StoreOrder};
use Modules\Store\Services\Shipping\ShippingGatewayRegistry;

class StoreShipmentService {
    public function createForSubOrder(int $subOrderId): string {
        $shipment=DB::transaction(function() use($subOrderId) {
            $sub=StoreSubOrder::with('order')->lockForUpdate()->findOrFail($subOrderId);
            $shipment=StoreShipment::where('store_sub_order_id',$sub->id)->lockForUpdate()->firstOrFail();
            if(in_array($shipment->status,['created','awaiting_manual_dispatch','in_transit','delivered','cancelled','returned'],true)) return null;
            if($sub->status!=='processing'||!$sub->seller_ready_at||$sub->order->status==='cancelled'
                ||($sub->order->payment_method==='online'&&!$sub->collected_at))
                throw ValidationException::withMessages(['shipment'=>'الشحنة لم تستوفِ شروط التجهيز والتحصيل.']);
            if($shipment->status==='creating'||$shipment->status==='reconciliation_needed') {
                $shipment->update(['status'=>'reconciliation_needed']); return false;
            }
            $shipment->update(['status'=>'creating']);
            return $shipment;
        },3);
        if($shipment===null) return 'completed';
        if($shipment===false) return 'manual_review';
        $result=ShippingGatewayRegistry::make($shipment->integration_snapshot)->create($shipment);
        if(!($result['success']??false)) {
            $shipment->update(['status'=>($result['ambiguous']??false)?'reconciliation_needed':'failed']);
            return 'manual_review';
        }
        $shipment->update(['status'=>($result['manual']??false)?'awaiting_manual_dispatch':'created',
            'provider_reference'=>$result['provider_reference']??null,'tracking_number'=>$result['tracking_number']??null,
            'label_url'=>$result['label_url']??null]);
        return 'completed';
    }
    public function dispatchManual(StoreShipment $shipment,string $trackingNumber): void {
        DB::transaction(function() use($shipment,$trackingNumber) {
            $sub=StoreSubOrder::findOrFail($shipment->store_sub_order_id);
            StoreOrder::lockForUpdate()->findOrFail($sub->store_order_id);
            $shipment=StoreShipment::lockForUpdate()->findOrFail($shipment->id);
            if(($shipment->integration_snapshot['driver']??'')!=='manual'||$sub->status!=='processing'||!$sub->seller_ready_at)
                throw ValidationException::withMessages(['shipment'=>'لا يمكن تسجيل إرسال هذه الشحنة الآن.']);
            $shipment->update(['provider_reference'=>$trackingNumber,'tracking_number'=>$trackingNumber,'status'=>'created']);
            app(StoreOrderService::class)->updateSubOrderStatus($sub,'shipped',[
                'tracking_number'=>$trackingNumber,'shipping_carrier'=>$shipment->quote_snapshot['carrier_name'],
            ]);
        },3);
    }
    public function cancel(StoreShipment $shipment,bool $manualConfirmed=false): string {
        $driver=ShippingGatewayRegistry::make($shipment->integration_snapshot);
        if(($shipment->integration_snapshot['driver']??'')==='manual') {
            if(!$manualConfirmed) throw ValidationException::withMessages(['confirmed'=>'أكد إلغاء الشحنة لدى شركة الشحن قبل تحرير المخزون.']);
            $shipment->update(['status'=>'cancelled']);
            return 'completed';
        }
        $result=$driver->cancel($shipment);
        if(!($result['success']??false)) { $shipment->update(['status'=>'reconciliation_needed']); return 'manual_review'; }
        $shipment->update(['status'=>'cancelled']);
        return 'completed';
    }
}
