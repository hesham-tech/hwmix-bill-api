<?php
namespace Modules\Store\Services;
use App\Support\Money;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Payment\Models\PaymentTransaction;
use Modules\Payment\Services\PaymentGatewayFactory;
use Modules\Store\Models\{StoreOrder,StoreSubOrder,StoreRefund,StoreSellerAccount};

class StoreRefundService
{
    public function __construct(private StoreLedgerService $ledger,private StoreOutboxService $outbox) {}
    public function excessPayment(StoreOrder $order,PaymentTransaction $transaction): void {
        $amount=Money::minor($transaction->amount);
        $this->ledger->journal('payment:'.$transaction->id.':receipt','excess_customer_payment',$order->currency,[
            ['account_code'=>'asset:gateway_receivable','amount_minor'=>$amount],['account_code'=>'clearing:customer_receipts','amount_minor'=>-$amount],
        ],['order_id'=>$order->id,'transaction_id'=>$transaction->id]);
        $refund=StoreRefund::firstOrCreate(['operation_key'=>'excess:'.$transaction->id],[
            'store_order_id'=>$order->id,'payment_transaction_id'=>$transaction->id,'amount_minor'=>$amount,'reason'=>'duplicate_capture',
            'allocation_snapshot'=>['excess_capture'=>true],
        ]);
        $this->ledger->journal('refund:'.$refund->id.':allocation','excess_payment_refund',$order->currency,[
            ['account_code'=>'clearing:customer_receipts','amount_minor'=>$amount],['account_code'=>'liability:customer_refunds','amount_minor'=>-$amount],
        ],['refund_id'=>$refund->id]);
        $this->outbox->enqueue('refund.process','refund:'.$refund->id,['refund_id'=>$refund->id]);
        app(StoreOrderService::class)->event($order,'payment.excess_capture',['transaction_id'=>$transaction->id,'refund_id'=>$refund->id]);
    }
    public function confirmManual(StoreRefund $refund,string $reference,string $payer): void {
        if($refund->status==='completed') {
            if($refund->provider_reference!==$reference) throw ValidationException::withMessages(['reference'=>'الاسترداد موثق بمرجع مختلف.']);
            return;
        }
        if($payer==='gateway') {
            if(!$refund->payment_transaction_id) throw \Illuminate\Validation\ValidationException::withMessages(['payer'=>'هذه العملية لم تحصّلها بوابة إلكترونية.']);
            $transaction=PaymentTransaction::withoutGlobalScopes()->findOrFail($refund->payment_transaction_id);
            $driver=PaymentGatewayFactory::forTransaction($transaction);
            if(!method_exists($driver,'refundStatus')||!(($result=$driver->refundStatus($reference,$transaction,$refund->amount_minor))['success']??false)||($result['status']??'')!=='refunded')
                throw \Illuminate\Validation\ValidationException::withMessages(['reference'=>'لم يثبت المزود اكتمال هذا الاسترداد بالقيمة والعملية الصحيحين.']);
        }
        DB::transaction(function() use($refund,$reference,$payer) {
            $locked=StoreRefund::lockForUpdate()->findOrFail($refund->id);
            if($locked->status==='completed') return;
            $order=StoreOrder::lockForUpdate()->findOrFail($locked->store_order_id);
            $codSeller=(bool)($locked->allocation_snapshot['cod_seller']??false);
            if($payer==='seller'&&!$codSeller) throw \Illuminate\Validation\ValidationException::withMessages(['payer'=>'البائع ليس جهة التحصيل الأصلية لهذه العملية.']);
            if($locked->status==='processing') throw \Illuminate\Validation\ValidationException::withMessages(['refund'=>'توجد محاولة استرداد قيد المعالجة؛ طابق نتيجتها قبل أي تحويل يدوي.']);
            $counter=$payer==='gateway'?'asset:gateway_receivable':($payer==='seller'?'asset:seller_refund_receivable':'asset:bank_clearing');
            $proofs=app(StorePaymentProofService::class);$proofs->claim($proofs->refundScope($locked,$payer),$reference,$locked,$locked->amount_minor,$order->currency);
            $this->ledger->journal('refund:'.$locked->id.':paid','customer_refunded',$order->currency,[
                ['account_code'=>'liability:customer_refunds','amount_minor'=>$locked->amount_minor],['account_code'=>$counter,'amount_minor'=>-$locked->amount_minor],
            ],['refund_id'=>$locked->id,'reference'=>$reference,'payer'=>$payer,'actor_id'=>auth()->id()]);
            if($codSeller&&$payer==='platform') {
                $sub=StoreSubOrder::findOrFail($locked->store_sub_order_id);
                $this->ledger->post($this->ledger->account($sub->company_id,$order->currency),'balance_minor',-$locked->amount_minor,
                    'refund:'.$locked->id.':seller-funded','platform_advanced_customer_refund','asset:seller_refund_receivable');
            }
            if($locked->payment_transaction_id) {
                $transaction=PaymentTransaction::withoutGlobalScopes()->lockForUpdate()->findOrFail($locked->payment_transaction_id);
                $refunded=(int)$transaction->refunded_minor+$locked->amount_minor;
                if($refunded>Money::minor($transaction->amount)) throw new \LogicException('Refund exceeds captured amount.');
                $transaction->update(['refunded_minor'=>$refunded,'status'=>$refunded===Money::minor($transaction->amount)?'refunded':'partially_refunded']);
            }
            $locked->update(['status'=>'completed','completed_at'=>now('UTC'),'provider_reference'=>$reference]);
            $total=(int)StoreRefund::where('store_order_id',$order->id)->where('status','completed')->get()->reject(fn($r)=>(bool)($r->allocation_snapshot['excess_capture']??false))->sum('amount_minor');
            if(!($locked->allocation_snapshot['excess_capture']??false)) $order->update(['payment_status'=>$total>=Money::minor($order->total_amount)?'refunded':'partial']);
            \Modules\Store\Models\StoreReturn::where('store_refund_id',$locked->id)->update(['status'=>'completed','completed_at'=>now('UTC')]);
            app(StoreOrderService::class)->event($order,'refund.manually_reconciled',['refund_id'=>$locked->id,'payer'=>$payer,'reference'=>$reference]);
            $this->outbox->enqueue('order.customer_notification','refund:'.$locked->id.':completed',['order_id'=>$order->id,'event'=>'refunded']);
        },3);
    }
    public function cancelSubOrder(StoreSubOrder $sub,string $reason): StoreRefund {
        $existing=StoreRefund::where('operation_key','cancel:'.$sub->id)->first();
        if($existing) return $existing;
        $transaction=PaymentTransaction::withoutGlobalScopes()->where('payable_type',StoreOrder::class)->where('payable_id',$sub->store_order_id)
            ->whereIn('status',['completed','partially_refunded'])->orderBy('id')->firstOrFail();
        $gross=Money::minor($sub->subtotal)+Money::minor($sub->tax_total)+Money::minor($sub->shipping_cost)-Money::minor($sub->discount_total)-(int)$sub->refunded_minor;
        $net=(int)$sub->financial_policy_snapshot['seller_net_minor']-(int)$sub->seller_reversed_minor;
        $commission=(int)$sub->commission_minor-(int)$sub->commission_reversed_minor;
        $refund=StoreRefund::create(['store_order_id'=>$sub->store_order_id,'store_sub_order_id'=>$sub->id,
            'payment_transaction_id'=>$transaction->id,'operation_key'=>'cancel:'.$sub->id,'amount_minor'=>$gross,
            'reason'=>$reason,'allocation_snapshot'=>['seller_minor'=>$net,'commission_minor'=>$commission,'platform_shipping_minor'=>$gross-$net-$commission]]);
        $account=$this->ledger->account($sub->company_id,$sub->order->currency);
        $clearing='clearing:refund:'.$refund->id;
        $this->ledger->post($account,$sub->settled_at?'balance_minor':'pending_minor',-$net,'refund:'.$refund->id.':seller','refund_reserved',$clearing,
            ['reference_type'=>'store_refund','reference_id'=>$refund->id]);
        $this->ledger->journal('refund:'.$refund->id.':allocation','refund_liability',$sub->order->currency,[
            ['account_code'=>$clearing,'amount_minor'=>$net],
            ['account_code'=>$sub->commission_recognized_at?'income:commission':'liability:commission_deferred','amount_minor'=>$commission],
            ...ShippingFinancials::allocation($sub->financial_policy_snapshot,-1),
            ['account_code'=>'liability:customer_refunds','amount_minor'=>-$gross],
        ],['refund_id'=>$refund->id]);
        $sub->update(['refunded_minor'=>$sub->refunded_minor+$gross,'seller_reversed_minor'=>$sub->seller_reversed_minor+$net,
            'commission_reversed_minor'=>$sub->commission_reversed_minor+$commission,'refund_requested_at'=>now('UTC')]);
        $this->outbox->enqueue('refund.process','refund:'.$refund->id,['refund_id'=>$refund->id]);
        return $refund;
    }
    public function unallocatedSubOrder(StoreSubOrder $sub,PaymentTransaction $transaction): void {
        if(StoreRefund::where('operation_key','unallocated:'.$sub->id)->exists()) return;
        $gross=Money::minor($sub->subtotal)+Money::minor($sub->tax_total)+Money::minor($sub->shipping_cost)-Money::minor($sub->discount_total);
        $refund=StoreRefund::create(['store_order_id'=>$sub->store_order_id,'store_sub_order_id'=>$sub->id,
            'payment_transaction_id'=>$transaction->id,'operation_key'=>'unallocated:'.$sub->id,'amount_minor'=>$gross,'reason'=>'item_cancelled_before_payment']);
        $this->ledger->journal('refund:'.$refund->id.':allocation','unallocated_payment_refund',$sub->order->currency,[
            ['account_code'=>'clearing:customer_receipts','amount_minor'=>$gross],
            ['account_code'=>'liability:customer_refunds','amount_minor'=>-$gross],
        ],['refund_id'=>$refund->id]);
        $sub->update(['refunded_minor'=>$gross,'refund_requested_at'=>now('UTC')]);
        $this->outbox->enqueue('refund.process','refund:'.$refund->id,['refund_id'=>$refund->id]);
    }
    public function latePayment(StoreOrder $order,PaymentTransaction $transaction): void {
        if(StoreRefund::where('operation_key','late:'.$transaction->id)->exists()) return;
        $amount=Money::minor($transaction->amount);
        $refund=StoreRefund::create(['store_order_id'=>$order->id,'payment_transaction_id'=>$transaction->id,
            'operation_key'=>'late:'.$transaction->id,'amount_minor'=>$amount,'reason'=>'payment_after_cancellation']);
        $this->ledger->journal('refund:'.$refund->id.':allocation','late_payment_refund',$order->currency,[
            ['account_code'=>'clearing:customer_receipts','amount_minor'=>$amount],
            ['account_code'=>'liability:customer_refunds','amount_minor'=>-$amount],
        ],['refund_id'=>$refund->id]);
        if($order->status!=='cancelled') {
            // A captured payment after expiry must never revive a released reservation.
            foreach($order->subOrders()->where('status','!=','cancelled')->get() as $sub) {
                app(StockReservationService::class)->releaseReservation($sub);
                app(SellerFinanceService::class)->release($sub);
                $sub->update(['status'=>'cancelled']);
                \Modules\Store\Models\StoreShipment::where('store_sub_order_id',$sub->id)->update(['status'=>'cancelled','cod_minor'=>0]);
            }
            $order->update(['status'=>'cancelled']);
        }
        $this->outbox->enqueue('refund.process','refund:'.$refund->id,['refund_id'=>$refund->id]);
    }
    public function process(StoreRefund $refund): string {
        $state=DB::transaction(function() use($refund) {
            if(!$refund->payment_transaction_id)return 'manual_review';
            $transaction=PaymentTransaction::withoutGlobalScopes()->lockForUpdate()->findOrFail($refund->payment_transaction_id);
            $locked=StoreRefund::lockForUpdate()->findOrFail($refund->id);
            if($locked->status==='completed') return 'completed';
            $reserved=StoreRefund::where('payment_transaction_id',$transaction->id)->where('status','!=','cancelled')->lockForUpdate()->get(['amount_minor'])->sum('amount_minor');
            if($reserved>Money::minor($transaction->amount)) {$locked->update(['status'=>'manual_review']);return 'manual_review';}
            if(in_array($locked->status,['processing','reconciliation_needed'],true)&&$transaction->gateway_driver!=='stripe'&&!$locked->provider_reference) {
                $locked->update(['status'=>'reconciliation_needed']);
                return 'manual_review';
            }
            $locked->update(['status'=>'processing']);
            return 'process';
        },3);
        if($state!=='process') return $state;
        $refund->refresh();
        $transaction=PaymentTransaction::withoutGlobalScopes()->findOrFail($refund->payment_transaction_id);
        $driver=PaymentGatewayFactory::forTransaction($transaction);
        $result=$refund->provider_reference&&method_exists($driver,'refundStatus')
            ?$driver->refundStatus($refund->provider_reference,$transaction,$refund->amount_minor)
            :$driver->refund($transaction,Money::decimal($refund->amount_minor),['idempotency_key'=>'store-refund-'.$refund->id]);
        if(!($result['success']??false)||($result['status']??'')!=='refunded') {
            $refund->update(['status'=>'reconciliation_needed','provider_reference'=>$result['gateway_reference']??null]);
            return ($result['success']??false)&&($result['status']??'')==='pending'?'pending':'manual_review';
        }
        if(empty($result['gateway_reference'])) {$refund->update(['status'=>'reconciliation_needed']);return 'manual_review';}
        $refund->update(['provider_reference'=>$result['gateway_reference']]);
        DB::transaction(function() use($refund,$transaction,$result) {
            $transaction=PaymentTransaction::withoutGlobalScopes()->lockForUpdate()->findOrFail($transaction->id);
            $locked=StoreRefund::lockForUpdate()->findOrFail($refund->id);
            if($locked->status==='completed') return;
            $order=StoreOrder::lockForUpdate()->findOrFail($locked->store_order_id);
            if((int)$transaction->refunded_minor+$locked->amount_minor>Money::minor($transaction->amount))throw new \LogicException('Refund exceeds captured amount.');
            $proofs=app(StorePaymentProofService::class);$proofs->claim($proofs->refundScope($locked,'gateway'),$result['gateway_reference'],$locked,$locked->amount_minor,$order->currency);
            $this->ledger->journal('refund:'.$locked->id.':paid','customer_refunded',$order->currency,[
                ['account_code'=>'liability:customer_refunds','amount_minor'=>$locked->amount_minor],
                ['account_code'=>'asset:gateway_receivable','amount_minor'=>-$locked->amount_minor],
            ],['provider_reference'=>$result['gateway_reference'],'refund_id'=>$locked->id]);
            $refunded=(int)$transaction->refunded_minor+$locked->amount_minor;
            $transaction->update(['refunded_minor'=>$refunded,'status'=>$refunded===Money::minor($transaction->amount)?'refunded':'partially_refunded']);
            if(!($locked->allocation_snapshot['excess_capture']??false)) $order->update(['payment_status'=>$refunded===Money::minor($order->total_amount)?'refunded':'partial']);
            $locked->update(['status'=>'completed','completed_at'=>now('UTC'),'provider_reference'=>$result['gateway_reference']]);
            \Modules\Store\Models\StoreReturn::where('store_refund_id',$locked->id)->update(['status'=>'completed','completed_at'=>now('UTC')]);
            app(StoreOrderService::class)->event($order,'refund.completed',['refund_id'=>$locked->id,'amount_minor'=>$locked->amount_minor]);
            $this->outbox->enqueue('order.customer_notification','refund:'.$locked->id.':completed',['order_id'=>$order->id,'event'=>'refunded']);
        },3);
        return 'completed';
    }
}
