<?php
namespace Modules\Store\Services;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Store\Models\{StoreOrder,StoreSubOrder,StoreShipment};
class StoreLocalDeliveryOperations {
    public function act(StoreSubOrder $sub,string $action,array $data): void {
        if(!in_array($action,['dispatch','delivered','collect','return_to_origin'],true)||!filter_var($data['confirmed']??false,FILTER_VALIDATE_BOOLEAN))
            throw ValidationException::withMessages(['confirmed'=>'أكد الإجراء الفعلي قبل تسجيله.']);
        DB::transaction(function()use($sub,$action,$data){
            StoreOrder::lockForUpdate()->findOrFail($sub->store_order_id);
            $sub=StoreSubOrder::lockForUpdate()->findOrFail($sub->id);
            $shipment=StoreShipment::where('store_sub_order_id',$sub->id)->lockForUpdate()->firstOrFail();
            if($shipment->delivery_method!=='seller_local')throw ValidationException::withMessages(['delivery'=>'هذا الإجراء للتوصيل الذاتي فقط.']);
            $orders=app(StoreOrderService::class);
            if($action==='collect'){app(StoreCodCollectionService::class)->record($sub,$data['reference']);return;}
            if($action==='return_to_origin'){
                app(StoreRtoService::class)->receive($sub,['confirmed'=>true,'condition'=>$data['condition'],'reason'=>$data['reason'],'carrier_reference'=>$data['reference'],'carrier_cost'=>'0.00']);return;
            }
            if($action==='dispatch'){
                if($sub->status==='shipped'&&$sub->tracking_number===$data['reference'])return;
                if($sub->status!=='processing'||!$sub->seller_ready_at||($sub->order->payment_method==='online'&&!$sub->collected_at))
                    throw ValidationException::withMessages(['delivery'=>'أكد الطلب وجهزه وتحقق من الدفع الإلكتروني قبل الخروج للتوصيل.']);
                $orders->updateSubOrderStatus($sub,'shipped',['tracking_number'=>$data['reference'],'shipping_carrier'=>'توصيل البائع','local_delivery_verified'=>true]);
            }else{
                if($sub->status==='delivered')return;
                $orders->updateSubOrderStatus($sub,'delivered',['local_delivery_verified'=>true]);
            }
            $orders->event($sub->order,'local_delivery.'.$action,['sub_order_id'=>$sub->id,'reference'=>$data['reference']??null],$sub->company_id);
            app(StoreOutboxService::class)->enqueue('order.vendor_notification','local:'.$sub->id.':'.$action,['order_id'=>$sub->store_order_id,'sub_order_id'=>$sub->id,'company_id'=>$sub->company_id,'event'=>'local_delivery.'.$action]);
        },3);
    }
}
