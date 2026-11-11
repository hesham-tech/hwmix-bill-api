<?php
namespace Modules\Store\Transformers;
use Illuminate\Http\Resources\Json\JsonResource;
class StoreSubOrderResource extends JsonResource {
    public function toArray($request): array {
        $result=[
            'id'=>$this->id,'store_order_id'=>$this->store_order_id,'company_id'=>$this->company_id,
            'sub_order_number'=>$this->sub_order_number,'status'=>$this->status,'subtotal'=>(string)$this->subtotal,
            'tax_total'=>(string)$this->tax_total,'shipping_cost'=>(string)$this->shipping_cost,'discount_total'=>(string)$this->discount_total,
            'total_amount'=>\App\Support\Money::decimal(\App\Support\Money::minor($this->subtotal)+\App\Support\Money::minor($this->tax_total)
                +\App\Support\Money::minor($this->shipping_cost)-\App\Support\Money::minor($this->discount_total)),
            'tracking_number'=>$this->tracking_number,'shipping_carrier'=>$this->shipping_carrier,
            'seller_ready_at'=>$this->seller_ready_at,'delivered_at'=>$this->delivered_at,
            'refunded_minor'=>(int)$this->refunded_minor,'created_at'=>$this->created_at,
            'vendor'=>new VendorResource($this->whenLoaded('company')),
            'items'=>$this->whenLoaded('items',fn()=>$this->items->map(fn($item)=>[
                'id'=>$item->id,'product_id'=>$item->product_id,'product_variant_id'=>$item->product_variant_id,
                'quantity'=>$item->quantity,'unit_price'=>(string)$item->unit_price,'subtotal'=>(string)$item->total_price,
                'tax_total'=>(string)$item->tax_total,'unit_name'=>$item->unit_name_snapshot,
                'return_policy'=>app(\Modules\Store\Services\StoreReturnPolicy::class)->resolve($item->return_policy_snapshot??[],$item->return_policy_snapshot??[]),
                'product'=>['id'=>$item->product_id,'name'=>$item->product_name_snapshot,'image'=>$item->variant_image_snapshot],
                'variant'=>['id'=>$item->product_variant_id,'name'=>$item->variant_sku_snapshot,'sku'=>$item->variant_sku_snapshot],
            ])),
        ];
        if($this->relationLoaded('order')) $result['order']=[
            'order_number'=>$this->order->order_number,'payment_method'=>$this->order->payment_method,
            'payment_status'=>$this->order->payment_status,'shipping_address'=>$this->order->shipping_address_snapshot,
            'customer_notes'=>$this->order->customer_notes,
        ];
        if($request->attributes->get('store_staff_view',false)) $result['financial']=[
            'commission_minor'=>(int)$this->commission_minor,'seller_net_minor'=>$this->financial_policy_snapshot['seller_net_minor']??null,
            'rto_fee_payer'=>$this->financial_policy_snapshot['rto_fee_payer']??'platform',
            'collected_at'=>$this->collected_at,'remitted_at'=>$this->remitted_at,'settlement_available_at'=>$this->settlement_available_at,
        ];
        return $result;
    }
}
