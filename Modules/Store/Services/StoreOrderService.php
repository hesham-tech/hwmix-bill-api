<?php
namespace Modules\Store\Services;
use App\Models\User;
use App\Support\Money;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Modules\Inventory\Models\ProductVariant;
use Modules\Store\Models\{StoreOrder,StoreSubOrder,StoreOrderItem,StoreCheckoutQuote,StoreShipment,StoreOrderEvent,StoreSellerProfile};
use Modules\Store\Events\OrderPlaced;

class StoreOrderService
{
    public function __construct(private StockReservationService $stockReservation,private StoreQuoteService $quotes,
        private SellerPolicyService $policies,private SellerFinanceService $finance,private StoreOutboxService $outbox,
        private StoreSettingsService $settings) {}
    public function event(StoreOrder $order,string $type,array $metadata=[],?int $companyId=null): void {
        StoreOrderEvent::create(['store_order_id'=>$order->id,'company_id'=>$companyId,'actor_id'=>auth()->id(),
            'type'=>$type,'metadata'=>$metadata,'created_at'=>now('UTC')]);
    }
    public function trackingToken(StoreOrder $order): string {
        return hash_hmac('sha256','store-order:'.$order->checkout_key,config('app.key'));
    }
    public function placeOrder(array $data,?User $customer=null): StoreOrder {
        app(StoreOperationalHealth::class)->assertAcceptingOrders();
        return DB::transaction(function() use($data,$customer) {
            if ($customer) User::withoutGlobalScopes()->lockForUpdate()->findOrFail($customer->id);
            $fingerprint=$this->quotes->fingerprint($data,$customer);
            $existing=StoreOrder::where('checkout_key',$data['checkout_key'])->lockForUpdate()->first();
            if($existing) {
                if((string)$existing->customer_user_id!==(string)$customer?->id||!hash_equals($existing->checkout_fingerprint,$fingerprint))
                    throw ValidationException::withMessages(['checkout_key'=>'المحاولة مرتبطة بطلب مختلف.']);
                return $existing->load(['subOrders.company','subOrders.items','shippingAddress']);
            }
            $quote=StoreCheckoutQuote::lockForUpdate()->findOrFail($data['quote_id']);
            if(!$this->settings->get()['enabled']||$quote->expires_at->isPast()||$quote->checkout_key!==$data['checkout_key']
                ||(string)$quote->customer_user_id!==(string)$customer?->id||!hash_equals($quote->request_fingerprint,$fingerprint))
                throw ValidationException::withMessages(['quote_id'=>'انتهى عرض السعر أو تغيرت بياناته. اطلب عرضًا جديدًا.']);
            $snapshot=$quote->snapshot;
            $currentSettings=$this->settings->get();
            if (!$currentSettings[$snapshot['payment_method']==='cod'?'cod_enabled':'online_enabled']) throw ValidationException::withMessages(['payment_method'=>'طريقة الدفع لم تعد متاحة لطلبات جديدة.']);
            if ($customer && StoreOrder::where('customer_user_id',$customer->id)->where('status','pending')->count()>=(int)$currentSettings['max_unconfirmed_orders_per_customer'])
                throw ValidationException::withMessages(['orders'=>'لديك طلبات غير مؤكدة. أكملها أو ألغِ غير المطلوب قبل إنشاء طلب آخر.']);
            if ($snapshot['payment_method']==='online' && !in_array((int)($data['payment_gateway_id']??0),array_column(app(StorePaymentService::class)->gateways(),'id'),true))
                throw ValidationException::withMessages(['payment_gateway_id'=>'اختر حساب دفع متاحًا للمتجر.']);
            if ((!$currentSettings['allow_guest_checkout'] && !$customer)
                || ($customer && $currentSettings['require_verified_email'] && (!$customer->email || !$customer->email_verified_at))) {
                throw ValidationException::withMessages(['account'=>'يلزم حساب وبريد موثّق لإتمام الشراء.']);
            }
            $expected=array_column($snapshot['legal'],'version_id'); sort($expected);
            $accepted=array_map('intval',$data['legal_version_ids']); sort($accepted);
            $current=array_column($this->quotes->documents(),'version_id'); sort($current);
            if($accepted!==$expected||$current!==$expected)
                throw ValidationException::withMessages(['legal_version_ids'=>'راجع الإصدارات الحالية من شروط المتجر.']);
            foreach($snapshot['groups'] as $group) {
                $profile=StoreSellerProfile::where('company_id',$group['company_id'])->first();
                if(!$profile?->is_enabled||$profile->policy_review_required||(int)$profile->warehouse_id!==(int)$group['shipping']['warehouse_id']
                    ||!$this->policies->canPublish($group['company_id'])
                    ||$this->policies->hash($this->policies->resolve($group['company_id']))!==$group['policy_hash'])
                    throw ValidationException::withMessages(['quote_id'=>'تغيرت أهلية أحد البائعين. راجع عرض السعر.']);
                foreach($group['items'] as $item) {
                    $variant=ProductVariant::withoutGlobalScopes()->with(['product'=>fn($q)=>$q->withoutGlobalScopes()])->where('status','active')->find($item['variant_id']);
                    if(!$variant||!$variant->product?->active||!$variant->product?->is_active_in_store||$variant->product?->deleted_at
                        ||(int)$variant->product_id!==(int)$item['product_id']||(int)$variant->company_id!==(int)$group['company_id']
                        ||Money::minor((string)$variant->retail_price)!==$item['unit_price_minor'])
                        throw ValidationException::withMessages(['quote_id'=>'تغير سعر أو توفر أحد الخيارات. راجع عرض السعر.']);
                    $unit=$variant->display_unit_id?:($variant->product->display_unit_id?:($variant->base_unit_id?:$variant->product->base_unit_id));
                    if ((int)$unit!==(int)$item['selling_unit_id']) throw ValidationException::withMessages(['quote_id'=>'تغيرت وحدة البيع. راجع عرض السعر.']);
                    $base=$variant->base_unit_id?:$variant->product->base_unit_id;
                    $factor=(int)$unit===(int)$base?'1.000000':(string)(\Modules\Inventory\Models\ProductVariantUnit::withoutGlobalScopes()->where('product_variant_id',$variant->id)->where('unit_id',$unit)->value('conversion_factor_to_base')??'0');
                    if((int)$base!==(int)($item['base_unit_id']??0)||bccomp($factor,(string)$item['conversion_factor'],6)!==0)
                        throw ValidationException::withMessages(['quote_id'=>'تغير تحويل وحدة البيع. اطلب عرض سعر جديدًا.']);
                }
                $shippingAccount=\Modules\Store\Models\StoreShippingAccount::find($group['shipping']['shipping_account_id']);
                if (!$shippingAccount?->is_active) throw ValidationException::withMessages(['shipping'=>'حساب الشحن المختار متوقف. اطلب عرضًا جديدًا.']);
            }
            $order=StoreOrder::create([
                'order_number'=>'ORD-'.Str::ulid(),'customer_user_id'=>$customer?->id,'shipping_address_id'=>$snapshot['shipping_address_id'],
                'checkout_key'=>$data['checkout_key'],'checkout_fingerprint'=>$fingerprint,'payment_method'=>$snapshot['payment_method'],
                'payment_status'=>'unpaid','subtotal'=>Money::decimal($snapshot['subtotal_minor']),
                'shipping_total'=>Money::decimal($snapshot['shipping_minor']),'tax_total'=>Money::decimal($snapshot['tax_minor']),
                'total_amount'=>Money::decimal($snapshot['total_minor']),'customer_notes'=>$snapshot['notes'],
                'shipping_address_snapshot'=>$snapshot['address'],'currency'=>$snapshot['currency'],
                'hold_expires_at'=>now('UTC')->addHours($snapshot['hold_hours']),
                'policy_snapshot'=>['tax_mode'=>$snapshot['tax_mode'],'hold_hours'=>$snapshot['hold_hours']],
                'legal_snapshot'=>['versions'=>$snapshot['legal'],'accepted_at'=>now('UTC')->toIso8601String(),'channel'=>'authenticated_checkout','customer_user_id'=>$customer?->id],
            ]);
            $order->update(['tracking_token_hash'=>hash('sha256',$this->trackingToken($order))]);
            foreach($snapshot['groups'] as $index=>$group) {
                $shipping=$group['shipping'];
                $financial=array_merge($group['policy'],$group['shipping_financials']??[],[
                    'seller_net_minor'=>$group['seller_net_minor'],'policy_hash'=>$group['policy_hash'],
                    'platform_shipping_due_minor'=>$group['shipping_financials']['platform_shipping_due_minor']??($shipping['shipping_fee_beneficiary']==='platform'?$shipping['fee_minor']:0),
                    'cod_beneficiary'=>$shipping['cod_beneficiary'],
                ]);
                $sub=StoreSubOrder::create(['store_order_id'=>$order->id,'company_id'=>$group['company_id'],
                    'sub_order_number'=>$order->order_number.'-'.($index+1),'status'=>'pending',
                    'subtotal'=>Money::decimal($group['subtotal_minor']),'tax_total'=>Money::decimal($group['tax_minor']),
                    'shipping_cost'=>Money::decimal($shipping['fee_minor']),'commission_minor'=>$group['commission_minor'],
                    'financial_policy_snapshot'=>$financial]);
                foreach($group['items'] as $item) StoreOrderItem::create([
                    'store_order_id'=>$order->id,'store_sub_order_id'=>$sub->id,'product_variant_id'=>$item['variant_id'],
                    'product_id'=>$item['product_id'],'company_id'=>$group['company_id'],'product_name_snapshot'=>$item['product_name'],
                    'variant_sku_snapshot'=>$item['sku'],'variant_image_snapshot'=>$item['image'],
                    'unit_price'=>Money::decimal($item['unit_price_minor']),'quantity'=>$item['quantity'],
                    'total_price'=>Money::decimal($item['total_minor']),'tax_total'=>Money::decimal($item['tax_minor']),
                    'selling_unit_id'=>$item['selling_unit_id'],'unit_name_snapshot'=>$item['unit_name'],
                    'conversion_factor_snapshot'=>$item['conversion_factor'],'base_quantity'=>$item['base_quantity'],
                    'return_policy_snapshot'=>$item['return_policy'],
                ]);
                $this->stockReservation->reserveForSubOrder($sub,$sub->items,$shipping['warehouse_id']);
                if($order->payment_method==='cod'&&$shipping['cod_beneficiary']==='seller')
                    $this->finance->reserve($sub,$group['commission_minor']+$financial['platform_shipping_due_minor'],$financial);
                StoreShipment::create(['store_sub_order_id'=>$sub->id,'company_id'=>$sub->company_id,
                    'shipping_account_id'=>$shipping['shipping_account_id'],'idempotency_key'=>(string)Str::uuid(),
                    'integration_snapshot'=>$shipping['integration'],'pickup_address'=>$shipping['pickup_address'],
                    'delivery_address'=>$snapshot['address'],'quote_snapshot'=>array_diff_key($shipping,['integration'=>true]),
                    'cod_beneficiary'=>$shipping['cod_beneficiary'],'currency'=>$order->currency,
                    'cod_minor'=>$order->payment_method==='cod'?$group['subtotal_minor']+$group['tax_minor']+$shipping['fee_minor']:0,
                ]);
                $this->outbox->enqueue('order.vendor_notification','order:'.$order->id.':vendor:'.$sub->company_id,
                    ['order_id'=>$order->id,'sub_order_id'=>$sub->id,'company_id'=>$sub->company_id]);
            }
            $this->event($order,'order.created');
            $this->outbox->enqueue('order.customer_notification','order:'.$order->id.':placed',['order_id'=>$order->id,'event'=>'placed']);
            DB::afterCommit(fn()=>event(new OrderPlaced($order)));
            return $order->load(['subOrders.company','subOrders.items','shippingAddress']);
        },3);
    }
    public function cancelOrder(StoreOrder $order,string $reason='customer_cancellation'): void {
        DB::transaction(function() use($order,$reason) {
            $order=StoreOrder::lockForUpdate()->findOrFail($order->id);
            if ($reason==='hold_expired' && $order->status!=='pending') return;
            if($order->status==='cancelled') return;
            if($order->subOrders()->whereIn('status',['processing','shipped','delivered'])->exists()) {
                if(!$order->cancellation_requested_at) {
                    $order->update(['cancellation_requested_at'=>now('UTC')]);
                    $this->event($order,'order.cancellation_requested');
                }
                return;
            }
            foreach($order->subOrders()->orderBy('company_id')->get() as $sub) $this->cancelSubOrder($sub,$reason);
            $this->syncParentOrderStatus($order);
            if ($reason==='hold_expired') $this->event($order,'order.hold_expired');
        },3);
    }
    public function cancelSubOrder(StoreSubOrder $sub,string $reason,bool $receivedBack=false): void {
        $order=StoreOrder::lockForUpdate()->findOrFail($sub->store_order_id);
        $sub=StoreSubOrder::lockForUpdate()->findOrFail($sub->id);
        if($sub->status==='cancelled') return;
        if($sub->status==='delivered'||($sub->status==='shipped'&&!$receivedBack))
            throw ValidationException::withMessages(['status'=>'هذه الشحنة تتطلب مسار الإرجاع.']);
        $shipment=StoreShipment::where('store_sub_order_id',$sub->id)->first();
        if($receivedBack&&$shipment?->status!=='returned')throw ValidationException::withMessages(['shipping'=>'إثبات استلام رجوع الشحنة مطلوب قبل تحرير المخزون.']);
        if($shipment?->provider_reference && !in_array($shipment->status,['cancelled','returned'],true))
            throw ValidationException::withMessages(['shipping'=>'يجب تأكيد إلغاء البوليصة قبل تحرير المخزون.']);
        $this->stockReservation->releaseReservation($sub);
        $this->finance->release($sub);
        $sub->update(['status'=>'cancelled']);
        $shipment?->update(['status'=>$receivedBack?'returned':'cancelled','cod_minor'=>0]);
        if($order->payment_method==='online'&&in_array($order->payment_status,['paid','partial'],true))
            app(StoreRefundService::class)->cancelSubOrder($sub,$reason);
        elseif($order->payment_method==='cod') {
            $amount=Money::minor($sub->subtotal)+Money::minor($sub->tax_total)+Money::minor($sub->shipping_cost)-Money::minor($sub->discount_total);
            $order->update(['total_amount'=>Money::decimal(max(0,Money::minor($order->total_amount)-$amount))]);
        }
        $this->event($order,'sub_order.cancelled',['sub_order_id'=>$sub->id,'reason'=>$reason],$sub->company_id);
        $this->syncParentOrderStatus($order);
        $this->outbox->enqueue('order.customer_notification','cancel:'.$sub->id,['order_id'=>$order->id,'event'=>'partial_cancellation','sub_order_id'=>$sub->id]);
    }
    public function confirm(StoreOrder $order,bool $phoneConfirmed=false): void {
        DB::transaction(function() use($order,$phoneConfirmed) {
            $order=StoreOrder::lockForUpdate()->findOrFail($order->id);
            if($order->status!=='pending') return;
            if($order->hold_expires_at?->isPast())
                throw ValidationException::withMessages(['order'=>'انتهت مدة حجز الطلب.']);
            if($order->payment_method==='cod'&&!$phoneConfirmed)
                throw ValidationException::withMessages(['phone_confirmed'=>'أكد التواصل مع العميل قبل اعتماد طلب الدفع عند الاستلام.']);
            if($order->payment_method==='online'&&$order->payment_status!=='paid')
                throw ValidationException::withMessages(['payment'=>'لم يُثبت التحصيل الإلكتروني بعد.']);
            $order->update(['status'=>'confirmed','confirmed_at'=>now('UTC'),'hold_expires_at'=>null]);
            foreach($order->subOrders()->where('status','pending')->get() as $sub) {
                $sub->update(['status'=>$sub->seller_ready_at?'processing':'confirmed']);
                if($sub->seller_ready_at) $this->outbox->enqueue('shipment.create','shipment:create:'.$sub->id,['sub_order_id'=>$sub->id]);
            }
            $this->event($order,'order.confirmed',['phone_confirmed'=>$phoneConfirmed]);
        },3);
    }
    public function sellerReady(StoreSubOrder $sub): void {
        DB::transaction(function() use($sub) {
            $order=StoreOrder::lockForUpdate()->findOrFail($sub->store_order_id);
            $sub=StoreSubOrder::lockForUpdate()->findOrFail($sub->id);
            if(!in_array($sub->status,['pending','confirmed','processing'],true))
                throw ValidationException::withMessages(['status'=>'لا يمكن تجهيز هذه الشحنة الآن.']);
            $sub->update(['seller_ready_at'=>now('UTC'),'status'=>$sub->status==='confirmed'?'processing':$sub->status]);
            if($sub->status==='processing') $this->outbox->enqueue('shipment.create','shipment:create:'.$sub->id,['sub_order_id'=>$sub->id]);
            $this->event($order,'sub_order.seller_ready',['sub_order_id'=>$sub->id],$sub->company_id);
            $this->syncParentOrderStatus($order);
        },3);
    }
    public function updateSubOrderStatus(StoreSubOrder $sub,string $status,array $extra=[]): void {
        DB::transaction(function() use($sub,$status,$extra) {
            $order=StoreOrder::lockForUpdate()->findOrFail($sub->store_order_id);
            $sub=StoreSubOrder::lockForUpdate()->findOrFail($sub->id);
            if($sub->status===$status) return;
            if($status==='cancelled') { $this->cancelSubOrder($sub,$extra['reason']??'admin_cancellation'); return; }
            $allowed=['pending'=>['confirmed'],'confirmed'=>['processing'],'processing'=>['shipped'],'shipped'=>['delivered']];
            if(!in_array($status,$allowed[$sub->status]??[],true))
                throw ValidationException::withMessages(['status'=>'تغيير حالة الطلب غير مسموح بهذا الترتيب.']);
            if($status==='confirmed') { $this->confirm($order,(bool)($extra['phone_confirmed']??false)); return; }
            if($status==='processing') { $this->sellerReady($sub); return; }
            $old=$sub->status;
            $sub->update(array_merge(['status'=>$status],array_intersect_key($extra,array_flip(['tracking_number','shipping_carrier']))));
            if($status==='shipped') StoreShipment::where('store_sub_order_id',$sub->id)->update(['status'=>'in_transit']);
            if($status==='delivered') {
                $this->stockReservation->consumeReservation($sub);
                $days=max(0,(int)$sub->items->max(fn($item)=>$item->return_policy_snapshot['window_days']??0));
                $sub->update(['delivered_at'=>now('UTC'),'settlement_available_at'=>now('UTC')->addDays($days+(int)($sub->financial_policy_snapshot['settlement_delay_days']??0))]);
                StoreShipment::where('store_sub_order_id',$sub->id)->update(['status'=>'delivered']);
                if($order->payment_method==='cod'&&($extra['collected']??false)) app(StorePaymentService::class)->confirmCodCollection($sub);
                elseif($order->payment_method==='online') $this->finance->recognizePrepaidCommission($sub);
                if ($order->payment_method==='online' || $sub->refresh()->collected_at) $this->outbox->enqueue('invoice.create','invoice:'.$sub->id,['sub_order_id'=>$sub->id]);
            }
            $this->event($order,'sub_order.status_changed',['sub_order_id'=>$sub->id,'from'=>$old,'to'=>$status],$sub->company_id);
            $this->syncParentOrderStatus($order);
            $this->outbox->enqueue('order.customer_notification','status:'.$sub->id.':'.$status,['order_id'=>$order->id,'sub_order_id'=>$sub->id,'event'=>$status]);
        },3);
    }
    public function syncParentOrderStatus(StoreOrder $order): void {
        $statuses=$order->subOrders()->pluck('status')->all();
        $active=array_values(array_filter($statuses,fn($s)=>$s!=='cancelled'));
        $status=match(true) {
            !$active=>'cancelled',
            count(array_filter($active,fn($s)=>$s==='delivered'))===count($active)=>'delivered',
            count(array_filter($active,fn($s)=>in_array($s,['shipped','delivered'],true)))===count($active)=>'shipped',
            (bool)array_intersect($active,['shipped','delivered'])=>'partially_shipped',
            in_array('processing',$active,true)=>'processing',
            in_array('confirmed',$active,true)=>'confirmed',
            default=>'pending',
        };
        $order->update(['status'=>$status]);
    }
}
