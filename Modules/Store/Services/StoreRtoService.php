<?php
namespace Modules\Store\Services;
use App\Support\Money;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Store\Models\{StoreOrder,StoreSubOrder,StoreShipment,StoreReturnToOrigin,StoreSellerAccount,StoreAccountHold,StoreStockAllocation};
class StoreRtoService {
    public function receive(StoreSubOrder $sub,array $data): StoreReturnToOrigin {
        if(!filter_var($data['confirmed']??false,FILTER_VALIDATE_BOOLEAN))throw ValidationException::withMessages(['confirmed'=>'يجب تأكيد الاستلام والفحص الفعلي ومطابقة تكلفة الناقل.']);
        return DB::transaction(function()use($sub,$data){
            $order=StoreOrder::lockForUpdate()->findOrFail($sub->store_order_id);
            $sub=StoreSubOrder::lockForUpdate()->findOrFail($sub->id);$sub->setRelation('order',$order);
            $shipment=StoreShipment::where('store_sub_order_id',$sub->id)->lockForUpdate()->firstOrFail();
            $fee=Money::minor((string)$data['carrier_cost']);
            if($shipment->delivery_method==='seller_local'&&$fee!==0)throw ValidationException::withMessages(['carrier_cost'=>'لا توجد تكلفة ناقل خارجي للتوصيل الذاتي.']);
            if($fee<0||!in_array($data['condition'],['saleable','damaged'],true))throw ValidationException::withMessages(['rto'=>'بيانات الفحص أو التكلفة غير صحيحة.']);
            $payer=$sub->financial_policy_snapshot['rto_fee_payer']??'platform';
            $fingerprint=hash('sha256',json_encode([$data['condition'],$data['reason'],$data['carrier_reference'],$fee,$payer],JSON_THROW_ON_ERROR));
            $existing=StoreReturnToOrigin::where('store_sub_order_id',$sub->id)->lockForUpdate()->first();
            if($existing){if(!hash_equals($existing->fingerprint,$fingerprint))throw ValidationException::withMessages(['rto'=>'سبق توثيق رجوع الشحنة ببيانات مختلفة.']);return $existing;}
            if($sub->status!=='shipped'||$sub->delivered_at||$sub->invoice_id||($order->payment_method==='cod'&&$sub->collected_at))
                throw ValidationException::withMessages(['rto'=>'هذا المسار للشحنة التي لم تسلم ولم يحصل منها دفع عند الاستلام. الطلب المسلّم يحتاج مسار المرتجعات.']);
            $rto=StoreReturnToOrigin::create(['store_sub_order_id'=>$sub->id,'fingerprint'=>$fingerprint,
                'condition'=>$data['condition'],'reason'=>$data['reason'],'carrier_reference'=>$data['carrier_reference'],
                'carrier_cost_minor'=>$fee,'cost_payer'=>$payer,'received_at'=>now('UTC'),'received_by'=>auth()->id()]);
            $loss=0;
            if($data['condition']==='damaged'){
                app(StockReservationService::class)->consumeReservation($sub);
                foreach(StoreStockAllocation::where('store_sub_order_id',$sub->id)->where('status','consumed')->get()->groupBy('store_order_item_id') as $allocations){
                    $raw=$allocations->reduce(fn($sum,$a)=>bcadd($sum,(string)$a->cost_total_snapshot,8),'0');
                    $loss+=Money::minor(bcdiv(bcadd(bcmul($raw,'100',8),'0.5',8),'1',0),0);
                }
            }
            $shipment->update(['status'=>'returned']);
            app(StoreOrderService::class)->cancelSubOrder($sub,'return_to_origin',true);
            $ledger=app(StoreLedgerService::class);$platformCarrier=$shipment->quote_snapshot['shipping_fee_beneficiary']==='platform';
            $clearing='clearing:rto:'.$rto->id;$lines=[];
            if($fee&&$platformCarrier){
                if($payer==='seller')$this->chargeSeller($ledger,$sub,$fee,$clearing,$rto->id);
                $ledger->journal('rto:'.$rto->id.':carrier','rto_carrier_cost',$order->currency,[
                    ['account_code'=>$payer==='seller'?$clearing:'expense:return_to_origin','amount_minor'=>$fee],
                    ['account_code'=>'liability:carrier','amount_minor'=>-$fee],
                ],['reference'=>$data['carrier_reference'],'company_id'=>$sub->company_id,'payer'=>$payer]);
            }elseif($fee&&$payer==='platform'){
                $ledger->post($ledger->account($sub->company_id,$order->currency),'balance_minor',$fee,'rto:'.$rto->id.':reimbursement',
                    'rto_cost_reimbursement','expense:return_to_origin',['reference_type'=>'store_rto','reference_id'=>$rto->id]);
            }
            if($fee&&($payer==='seller'||!$platformCarrier)){
                $lines[]=['type'=>'debit','account_type'=>'expense','sub_account_type'=>'shipping_return_to_origin','amount_minor'=>$fee];
                $lines[]=['type'=>'credit','account_type'=>$platformCarrier?'asset':'liability','sub_account_type'=>$platformCarrier?'marketplace_clearing':'courier_payable','amount_minor'=>$fee];
                if(!$platformCarrier&&$payer==='platform'){
                    $lines[]=['type'=>'debit','account_type'=>'asset','sub_account_type'=>'marketplace_clearing','amount_minor'=>$fee];
                    $lines[]=['type'=>'credit','account_type'=>'revenue','sub_account_type'=>'shipping_subsidy','amount_minor'=>$fee];
                }
            }
            if($loss){$lines[]=['type'=>'debit','account_type'=>'expense','sub_account_type'=>'inventory_writeoff','amount_minor'=>$loss];$lines[]=['type'=>'credit','account_type'=>'asset','sub_account_type'=>'inventory','amount_minor'=>$loss];}
            if($lines)$rto->update(['erp_operation_id'=>app(\App\Contracts\FinancialEngineInterface::class)->postMarketplaceJournal($rto,$sub->company_id,'marketplace_rto',$lines,
                ['amount'=>Money::decimal($fee+$loss),'currency'=>$order->currency,'entry_date'=>$rto->received_at->copy()->timezone(config('store.business_timezone','Africa/Cairo'))->toDateString(),'reference'=>$data['carrier_reference']])]);
            app(StoreOrderService::class)->event($order,'shipment.return_to_origin',['sub_order_id'=>$sub->id,'receipt_id'=>$rto->id,'condition'=>$data['condition'],'cost_minor'=>$fee,'cost_payer'=>$payer],$sub->company_id);
            app(StoreOutboxService::class)->enqueue('order.vendor_notification','rto:'.$rto->id.':vendor',['order_id'=>$order->id,'sub_order_id'=>$sub->id,'company_id'=>$sub->company_id,'event'=>'return_to_origin']);
            return $rto;
        },3);
    }
    private function chargeSeller(StoreLedgerService $ledger,StoreSubOrder $sub,int $fee,string $clearing,int $id): void {
        $account=$ledger->account($sub->company_id,$sub->order->currency);$account=StoreSellerAccount::lockForUpdate()->findOrFail($account->id);
        $holds=StoreAccountHold::where('account_id',$account->id)->where('status','active')->lockForUpdate()->get();
        $balance=min($fee,max(0,$account->balance_minor-$account->payout_held_minor-(int)$holds->sum('balance_minor')));
        $prepaid=min($fee-$balance,max(0,$account->prepaid_minor-(int)$holds->sum('prepaid_minor')));
        if($prepaid)$ledger->post($account,'prepaid_minor',-$prepaid,'rto:'.$id.':prepaid','rto_cost',$clearing);
        if($fee-$prepaid)$ledger->post($account,'balance_minor',-($fee-$prepaid),'rto:'.$id.':balance','rto_cost',$clearing);
        if($account->refresh()->balance_minor<0)app(StoreTaskScan::class)->review('rto-debt',$id,new \RuntimeException('Actual carrier cost requires settlement.'));
    }
}
