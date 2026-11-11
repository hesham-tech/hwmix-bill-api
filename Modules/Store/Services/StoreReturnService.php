<?php
namespace Modules\Store\Services;

use App\Support\Money;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Inventory\Models\Stock;
use Modules\Payment\Models\PaymentTransaction;
use Modules\Store\Models\{StoreOrder,StoreOrderItem,StoreSubOrder,StoreReturn,StoreRefund,StoreStockAllocation,StoreAccountHold};

class StoreReturnService
{
    private function fail(string $message): never { throw ValidationException::withMessages(['return'=>$message]); }
    private function share(int $amount,int $quantity,int $total): int { return (int)bcdiv(bcmul((string)$amount,(string)$quantity,0),(string)$total,0); }
    public function request(StoreOrder $order,array $data): StoreReturn {
        return DB::transaction(function() use($order,$data) {
            $order=StoreOrder::lockForUpdate()->findOrFail($order->id);
            $existing=StoreReturn::where('operation_key',$data['operation_key'])->first();
            if($existing) {
                if((int)$existing->item->store_order_id!==$order->id||(int)$existing->store_order_item_id!==(int)$data['item_id']
                    ||$existing->quantity!==(int)$data['quantity']||$existing->reason!==$data['reason']) $this->fail('مفتاح الطلب مرتبط بمرتجع مختلف.');
                return $existing;
            }
            $item=$order->items()->whereKey($data['item_id'])->firstOrFail();
            $sub=$item->subOrder;
            if($sub->status!=='delivered'||!$sub->delivered_at) $this->fail('يمكن طلب الإرجاع بعد التسليم.');
            $policy=app(StoreReturnPolicy::class)->resolve($item->return_policy_snapshot??[],$item->return_policy_snapshot??[]);
            $defect=in_array($data['reason'],['defect','wrong_item'],true);
            if(!($policy[$defect?'allow_defect':'allow_change_of_mind']??false)) $this->fail('سبب الإرجاع غير متاح وفق السياسة المحفوظة للمنتج.');
            $days=(int)$policy[$defect?'defect_days':'change_of_mind_days'];
            if(now('UTC')->gt($sub->delivered_at->copy()->addDays($days))) $this->fail('انتهت مهلة الإرجاع لهذا السبب؛ تواصل مع الدعم لحقوق الضمان الأخرى.');
            $reserved=(int)StoreReturn::where('store_order_item_id',$item->id)->whereNotIn('status',['rejected','cancelled'])->lockForUpdate()->get(['quantity'])->sum('quantity');
            if($reserved+(int)$data['quantity']>(int)$item->quantity) $this->fail('الكمية تتجاوز الكمية المتاحة للإرجاع.');
            $refund=$this->share(Money::minor($item->total_price)-Money::minor($item->discount_total),(int)$data['quantity'],(int)$item->quantity);
            $return=StoreReturn::create(['store_order_item_id'=>$item->id,'operation_key'=>$data['operation_key'],'quantity'=>$data['quantity'],
                'reason'=>$data['reason'],'shipping_payer'=>'seller','refund_minor'=>$refund,'customer_notes'=>$data['notes']??null]);
            app(StoreOrderService::class)->event($order,'return.requested',['return_id'=>$return->id,'item_id'=>$item->id],$sub->company_id);
            app(StoreOutboxService::class)->enqueue('order.vendor_notification','return:'.$return->id.':requested',['order_id'=>$order->id,'sub_order_id'=>$sub->id,'company_id'=>$sub->company_id,'event'=>'return_requested']);
            return $return;
        },3);
    }
    public function act(StoreReturn $return,array $data): StoreReturn {
        return DB::transaction(function() use($return,$data) {
            $order=StoreOrder::lockForUpdate()->findOrFail($return->item->store_order_id);
            $return=StoreReturn::lockForUpdate()->findOrFail($return->id);
            $action=$data['action'];
            if($action==='approve') {
                if($return->status==='approved') return $return;
                if($return->status!=='requested') $this->fail('لا يمكن اعتماد المرتجع في حالته الحالية.');
                $return->update(['status'=>'approved','admin_notes'=>$data['notes']??null]);
            } elseif($action==='reject') {
                if(!in_array($return->status,['requested','approved'],true)) $this->fail('لا يمكن رفض مرتجع تم استلامه.');
                if(empty($data['reason'])) $this->fail('سبب الرفض مطلوب.');
                $return->update(['status'=>'rejected','admin_notes'=>$data['reason']]);
            } else {
                if(in_array($return->status,['received','completed'],true)) return $return;
                if($return->status!=='approved') $this->fail('اعتمد طلب الإرجاع أولًا ثم وثق استلامه وفحصه.');
                $this->receive($return,$data);
            }
            app(StoreOrderService::class)->event($order,'return.'.$return->status,['return_id'=>$return->id],$return->item->company_id);
            app(StoreOutboxService::class)->enqueue('order.customer_notification','return:'.$return->id.':'.$return->status,['order_id'=>$order->id,'event'=>'return_'.$return->status]);
            return $return->refresh();
        },3);
    }
    private function receive(StoreReturn $return,array $data): void {
        $item=$return->item; $sub=StoreSubOrder::lockForUpdate()->findOrFail($item->store_sub_order_id); $order=$sub->order;
        // Accept the customer's request immediately; reconciliation must precede financial reversal.
        if(!$sub->collected_at) $this->fail('طلب العميل محفوظ؛ وثق التحصيل أو تسوية حالة الدفع قبل تنفيذ الرد المالي، دون تأخير حقوق العميل.');
        $prior=(int)StoreReturn::where('store_order_item_id',$item->id)->whereNotNull('received_at')->lockForUpdate()->get(['quantity'])->sum('quantity');
        $net=Money::minor($item->total_price)-Money::minor($item->tax_total)-Money::minor($item->discount_total);
        $goods=$this->share($net,$prior+$return->quantity,(int)$item->quantity)-$this->share($net,$prior,(int)$item->quantity);
        $tax=$this->share(Money::minor($item->tax_total),$prior+$return->quantity,(int)$item->quantity)-$this->share(Money::minor($item->tax_total),$prior,(int)$item->quantity);
        $previous=StoreReturn::whereHas('item',fn($q)=>$q->where('store_sub_order_id',$sub->id))->whereNotNull('received_at')->lockForUpdate()->get();
        $previousGoods=(int)$previous->sum(fn($r)=>(int)($r->inspection_snapshot['goods_minor']??0));
        $subtotal=Money::minor($sub->subtotal)-Money::minor($sub->discount_total);
        $commission=$subtotal>0?$this->share((int)$sub->commission_minor,$previousGoods+$goods,$subtotal)-(int)$sub->commission_reversed_minor:0;
        $commission=max(0,$commission);
        $shipping=$subtotal>0?$this->share(Money::minor($sub->shipping_cost),$previousGoods+$goods,$subtotal)-(int)$previous->sum(fn($r)=>(int)($r->inspection_snapshot['shipping_minor']??0)):0;
        $gross=$goods+$tax+$shipping;
        $codSeller=$order->payment_method==='cod'&&($sub->financial_policy_snapshot['cod_beneficiary']??'')==='seller';
        $transaction=$order->payment_method==='online'?PaymentTransaction::withoutGlobalScopes()->where('payable_type',$order->getMorphClass())->where('payable_id',$order->id)->whereIn('status',['completed','partially_refunded'])->firstOrFail():null;
        $allocation=['seller_minor'=>$gross-$commission,'commission_minor'=>$commission,'goods_minor'=>$goods,'tax_minor'=>$tax,
            'shipping_minor'=>$shipping,'cod_seller'=>$codSeller,'return_id'=>$return->id];
        $refund=StoreRefund::create(['store_order_id'=>$order->id,'store_sub_order_id'=>$sub->id,'payment_transaction_id'=>$transaction?->id,
            'operation_key'=>'return:'.$return->id,'amount_minor'=>$gross,'reason'=>'return_'.$return->reason,'allocation_snapshot'=>$allocation]);
        $ledger=app(StoreLedgerService::class); $account=$ledger->account($sub->company_id,$order->currency); $clearing='clearing:return:'.$return->id;
        $prepaidCredit=0;
        if($codSeller) {
            if($sub->commission_recognized_at) {
                $hold=StoreAccountHold::where('store_sub_order_id',$sub->id)->first();
                $obligation=$hold?($hold->balance_minor+$hold->prepaid_minor+$hold->credit_minor):0;
                $prepaidCredit=$obligation>0?$this->share((int)$hold->prepaid_minor,(int)$sub->commission_reversed_minor+$commission,$obligation)-(int)$previous->sum(fn($r)=>(int)($r->inspection_snapshot['prepaid_credit_minor']??0)):0;
                if($prepaidCredit>0) $ledger->post($account,'prepaid_minor',$prepaidCredit,'return:'.$return->id.':prepaid','return_commission',$clearing);
                if($commission-$prepaidCredit>0) $ledger->post($account,'balance_minor',$commission-$prepaidCredit,'return:'.$return->id.':commission','return_commission',$clearing);
                $ledger->journal('return:'.$return->id.':commission-allocation','commission_reversal',$order->currency,[['account_code'=>'income:commission','amount_minor'=>$commission],['account_code'=>$clearing,'amount_minor'=>-$commission]]);
            } else {
                $hold=StoreAccountHold::where('store_sub_order_id',$sub->id)->lockForUpdate()->first();
                if($hold&&$hold->status==='active') {
                    $remaining=$commission;
                    foreach(['credit_minor','prepaid_minor','balance_minor'] as $bucket) { $take=min($remaining,(int)$hold->$bucket);$hold->$bucket-=$take;$remaining-=$take; }
                    $hold->save();
                }
            }
            $ledger->journal('refund:'.$refund->id.':allocation','seller_customer_refund',$order->currency,[['account_code'=>'asset:seller_refund_receivable','amount_minor'=>$gross],['account_code'=>'liability:customer_refunds','amount_minor'=>-$gross]],['company_id'=>$sub->company_id,'refund_id'=>$refund->id]);
        } else {
            $netReversal=$gross-$commission; $pending=0;
            if(!$sub->settled_at) $pending=min($netReversal,max(0,(int)$sub->financial_policy_snapshot['seller_net_minor']-(int)$sub->seller_reversed_minor));
            if($pending>0) $ledger->post($account,'pending_minor',-$pending,'return:'.$return->id.':pending','return_refund',$clearing);
            if($netReversal-$pending>0) $ledger->post($account,'balance_minor',-($netReversal-$pending),'return:'.$return->id.':balance','return_refund',$clearing);
            $ledger->journal('refund:'.$refund->id.':allocation','return_refund',$order->currency,[['account_code'=>$clearing,'amount_minor'=>$netReversal],
                ['account_code'=>$sub->commission_recognized_at?'income:commission':'liability:commission_deferred','amount_minor'=>$commission],['account_code'=>'liability:customer_refunds','amount_minor'=>-$gross]],['refund_id'=>$refund->id]);
        }
        $sub->update(['refunded_minor'=>$sub->refunded_minor+$gross,'seller_reversed_minor'=>$sub->seller_reversed_minor+($codSeller?0:$gross-$commission),'commission_reversed_minor'=>$sub->commission_reversed_minor+$commission,'refund_requested_at'=>now('UTC')]);
        $restored=$this->restoreStock($return,$data['condition']); $cost=$restored['minor'];
        $return->update(['status'=>'received','received_at'=>now('UTC'),'condition'=>$data['condition'],'admin_notes'=>$data['notes']??null,
            'store_refund_id'=>$refund->id,'refund_minor'=>$gross,'inspection_snapshot'=>$allocation+['restored_cost_minor'=>$cost,'restored_cost_exact'=>$restored['exact'],'prepaid_credit_minor'=>$prepaidCredit]]);
        if($transaction) app(StoreOutboxService::class)->enqueue('refund.process','refund:'.$refund->id,['refund_id'=>$refund->id]);
        else $refund->update(['status'=>'manual_review']);
        app(StoreOutboxService::class)->enqueue('return.accounting','return:'.$return->id.':accounting',['return_id'=>$return->id]);
    }
    private function restoreStock(StoreReturn $return,string $condition): array {
        if($condition!=='saleable') return ['minor'=>0,'exact'=>'0.00000000'];
        $item=$return->item;
        $already=StoreReturn::where('store_order_item_id',$item->id)->whereNotNull('received_at')->sum('quantity');
        $start=bcmul((string)$already,$item->conversion_factor_snapshot,6); $end=bcadd($start,bcmul((string)$return->quantity,$item->conversion_factor_snapshot,6),6);
        $offset='0.000000'; $cost='0.00000000';
        foreach(StoreStockAllocation::where('store_order_item_id',$item->id)->where('status','consumed')->orderBy('id')->get() as $allocation) {
            $next=bcadd($offset,$allocation->quantity,6);
            $low=bccomp($start,$offset,6)>0?$start:$offset; $high=bccomp($end,$next,6)<0?$end:$next;
            if(bccomp($high,$low,6)>0) {
                $stock=Stock::withoutGlobalScopes()->lockForUpdate()->findOrFail($allocation->stock_id);
                if($stock->deleted_at||$stock->status!=='available'||($stock->expiry&&\Carbon\Carbon::parse($stock->expiry)->toDateString()<now('Africa/Cairo')->toDateString())) $this->fail('دفعة المخزون غير صالحة للإتاحة؛ سجل الاستلام كمنتج تالف أو افحص الدفعة أولًا.');
                $qty=bcsub($high,$low,6);$stock->quantity=bcadd($stock->quantity,$qty,6);$stock->saveQuietly();
                $cost=bcadd($cost,bcmul($qty,(string)$allocation->unit_cost_snapshot,8),8);
            }
            $offset=$next;
        }
        $previous=StoreReturn::where('store_order_item_id',$item->id)->whereNotNull('received_at')->get();
        $priorExact=$previous->reduce(fn($sum,$r)=>bcadd($sum,(string)($r->inspection_snapshot['restored_cost_exact']??bcdiv((string)($r->inspection_snapshot['restored_cost_minor']??0),'100',8)),8),'0');
        $priorMinor=(int)$previous->sum(fn($r)=>(int)($r->inspection_snapshot['restored_cost_minor']??0));
        return ['minor'=>(int)bcdiv(bcadd(bcmul(bcadd($cost,$priorExact,8),'100',8),'0.5',8),'1',0)-$priorMinor,'exact'=>$cost];
    }
    public function accounting(int $id): string {
        $return=StoreReturn::with('item.subOrder.order')->findOrFail($id); $sub=$return->item->subOrder;
        if(!$sub->invoice_id) return 'manual_review';
        $s=$return->inspection_snapshot; $codSeller=(bool)$s['cod_seller'];
        $goods=(int)$s['goods_minor'];$tax=(int)$s['tax_minor'];$commission=(int)$s['commission_minor'];
        $shipping=(int)$s['shipping_minor'];$cost=(int)$s['restored_cost_minor'];
        $lines=[['type'=>'debit','account_type'=>'revenue','sub_account_type'=>'marketplace_sales','amount_minor'=>$goods],
            ['type'=>'debit','account_type'=>'liability','sub_account_type'=>'sales_tax','amount_minor'=>$tax],
            ['type'=>'credit','account_type'=>'asset','sub_account_type'=>$codSeller?($sub->shipment?->delivery_method==='seller_local'?'seller_local_collections':'courier_receivable'):'marketplace_clearing','amount_minor'=>$goods+$tax-$commission],
            ['type'=>'credit','account_type'=>'expense','sub_account_type'=>'marketplace_commission','amount_minor'=>$commission]];
        if($shipping) {$lines[]=['type'=>'debit','account_type'=>'expense','sub_account_type'=>'return_shipping','amount_minor'=>$shipping];$lines[]=['type'=>'credit','account_type'=>'asset','sub_account_type'=>'marketplace_clearing','amount_minor'=>$shipping];}
        if($cost) {$lines[]=['type'=>'debit','account_type'=>'asset','sub_account_type'=>'inventory','amount_minor'=>$cost];$lines[]=['type'=>'credit','account_type'=>'expense','sub_account_type'=>'cost_of_goods_sold','amount_minor'=>$cost];}
        if($codSeller&&$commission) {
            // COD goods are refunded by the seller; the platform separately reverses its commission.
            $lines[2]['amount_minor']=$goods+$tax;
            $lines[]=['type'=>'debit','account_type'=>'asset','sub_account_type'=>'marketplace_clearing','amount_minor'=>$commission];
        }
        app(\App\Contracts\FinancialEngineInterface::class)->postMarketplaceJournal($return,$sub->company_id,'marketplace_return',$lines,['amount'=>Money::decimal($return->refund_minor),'currency'=>$sub->order->currency,'store_sub_order_id'=>$sub->id,'entry_date'=>$return->received_at]);
        app(StoreOutboxService::class)->enqueue('stats.daily','stats:return:'.$return->id,['company_id'=>$sub->company_id,'date'=>$return->received_at->copy()->timezone(config('store.business_timezone','Africa/Cairo'))->toDateString()]);
        return 'completed';
    }
}
