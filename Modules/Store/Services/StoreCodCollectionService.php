<?php
namespace Modules\Store\Services;
use App\Support\Money;
use Illuminate\Support\Facades\DB;
use Modules\Store\Models\{StoreOrder,StoreSubOrder,StoreShipment};
class StoreCodCollectionService {
    public function record(StoreSubOrder $sub,string $reference): void {
        DB::transaction(function()use($sub,$reference){
            StoreOrder::lockForUpdate()->findOrFail($sub->store_order_id);
            $locked=StoreSubOrder::lockForUpdate()->findOrFail($sub->id);
            $shipment=StoreShipment::where('store_sub_order_id',$locked->id)->firstOrFail();
            $gross=Money::minor($locked->subtotal)+Money::minor($locked->tax_total)+Money::minor($locked->shipping_cost)-Money::minor($locked->discount_total);
            $scope=$shipment->delivery_method==='seller_local'?'seller-local:'.$locked->company_id:'cod:carrier:'.$shipment->shipping_account_id;
            app(StorePaymentProofService::class)->claim($scope,$reference,$locked,$gross,$locked->order->currency);
            $wasCollected=(bool)$locked->collected_at;
            app(StorePaymentService::class)->confirmCodCollection($locked);
            if(!$wasCollected)app(StoreOrderService::class)->event($locked->order,'cod.collection_verified',['sub_order_id'=>$locked->id,'reference'=>$reference,'delivery_method'=>$shipment->delivery_method],$locked->company_id);
        },3);
    }
}
