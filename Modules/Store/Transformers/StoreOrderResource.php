<?php
namespace Modules\Store\Transformers;
use Illuminate\Http\Resources\Json\JsonResource;
class StoreOrderResource extends JsonResource {
    public function toArray($request): array {
        $attempt=$this->payment_method==='online'?\Modules\Payment\Models\PaymentTransaction::withoutGlobalScopes()
            ->where('payable_type',\Modules\Store\Models\StoreOrder::class)->where('payable_id',$this->id)
            ->whereNotIn('status',['failed','expired','cancelled'])->latest('id')->first(['id','status','gateway_driver']):null;
        return [
            'payment_attempt'=>$attempt?['status'=>$attempt->status,'provider'=>$attempt->gateway_driver]:null,
            'id'=>$this->id,'order_number'=>$this->order_number,'user_id'=>$this->customer_user_id,
            'subtotal'=>(string)$this->subtotal,'shipping_total'=>(string)$this->shipping_total,
            'tax_total'=>(string)$this->tax_total,'discount_total'=>(string)$this->discount_total,
            'total_amount'=>(string)$this->total_amount,'currency'=>$this->currency,
            'status'=>$this->status,'payment_method'=>$this->payment_method,'payment_status'=>$this->payment_status,
            'shipping_address'=>$this->shipping_address_snapshot??$this->whenLoaded('shippingAddress'),
            'customer_notes'=>$this->customer_notes,'created_at'=>$this->created_at,'hold_expires_at'=>$this->hold_expires_at,
            'cancellation_requested_at'=>$this->cancellation_requested_at,
            'sub_orders'=>StoreSubOrderResource::collection($this->whenLoaded('subOrders')),
            'refunds'=>$this->whenLoaded('refunds',fn()=>$this->refunds->map(fn($refund)=>[
                'id'=>$refund->id,'sub_order_id'=>$refund->store_sub_order_id,'amount_minor'=>$refund->amount_minor,'status'=>$refund->status,'excess_capture'=>(bool)($refund->allocation_snapshot['excess_capture']??false),
            ])),
        ];
    }
}
