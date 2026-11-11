<?php
namespace Modules\Store\Services;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Store\Models\{StoreOrder,StoreSubOrder,StoreShipment};
class StorePickupOverrideService {
    public function change(StoreSubOrder $sub,array $address,string $reason): void {
        DB::transaction(function()use($sub,$address,$reason){
            $order=StoreOrder::lockForUpdate()->findOrFail($sub->store_order_id);
            $sub=StoreSubOrder::lockForUpdate()->findOrFail($sub->id);
            $shipment=StoreShipment::where('store_sub_order_id',$sub->id)->lockForUpdate()->firstOrFail();
            $allowed=in_array($shipment->status,['waiting_ready','pending','awaiting_local_dispatch'],true)
                ||($shipment->status==='awaiting_manual_dispatch'&&($shipment->integration_snapshot['driver']??'')==='manual');
            if(!in_array($sub->status,['pending','confirmed','processing'],true)||!$allowed||$shipment->provider_reference)
                throw ValidationException::withMessages(['pickup'=>'بدأ إرسال الشحنة أو خرج الطلب للتوصيل؛ لا يمكن تغيير نقطة الاستلام الآن.']);
            $before=$shipment->pickup_address;
            $metadata=['reason'=>$reason,'actor_id'=>auth()->id(),'updated_at'=>now('UTC')->toIso8601String()];
            $shipment->update(['pickup_address'=>$address,'pickup_override'=>$metadata]);
            app(StoreOrderService::class)->event($order,'shipment.pickup_overridden',['sub_order_id'=>$sub->id,'warehouse_id'=>$shipment->quote_snapshot['warehouse_id'],
                'before'=>$before,'after'=>$address]+$metadata,$sub->company_id);
        },3);
    }
}
