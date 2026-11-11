<?php
namespace Modules\Store\Services;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Store\Models\StoreAccountHold;
use Modules\Store\Models\StoreSellerAccount;
use Modules\Store\Models\StoreSubOrder;

class SellerFinanceService
{
    public function __construct(private StoreLedgerService $ledger) {}
    public function commission(int $subtotal,array $policy): int {
        $type=$policy['commission_type'];
        if($type==='exempt') return 0;
        $percent=in_array($type,['percent','mixed'],true)?\App\Support\Money::minor((string)$policy['commission_percent']):0;
        $fixed=in_array($type,['fixed','mixed'],true)?(int)$policy['commission_fixed_minor']:0;
        $value=bcadd(bcdiv(bcadd(bcmul((string)$subtotal,(string)$percent,0),'5000',0),'10000',0),(string)$fixed,0);
        if(bccomp($value,'0',0)<0||bccomp($value,(string)$subtotal,0)>0)
            throw ValidationException::withMessages(['commission'=>'العمولة لا يمكن أن تتجاوز قيمة المنتجات.']);
        return (int)$value;
    }
    public function reserve(StoreSubOrder $subOrder,int $amount,array $policy): void {
        $account=$this->ledger->account($subOrder->company_id,$subOrder->order->currency);
        $account=StoreSellerAccount::lockForUpdate()->findOrFail($account->id);
        if(StoreAccountHold::where('store_sub_order_id',$subOrder->id)->exists()) return;
        $activeHolds=StoreAccountHold::where('account_id',$account->id)->where('status','active')->lockForUpdate()->get();
        $holds=(object)['b'=>$activeHolds->sum('balance_minor'),'p'=>$activeHolds->sum('prepaid_minor'),'c'=>$activeHolds->sum('credit_minor')];
        $available=($policy['use_sales_balance_for_commission']??true)?max(0,$account->balance_minor-(int)$holds->b-$account->payout_held_minor):0;
        $balance=min($amount,$available);
        $prepaid=min($amount-$balance,max(0,$account->prepaid_minor-(int)$holds->p));
        $credit=$amount-$balance-$prepaid;
        $debt=max(0,-$account->balance_minor)+(int)$holds->c;
        if($credit>0&&(($policy['funding_mode']??'prepaid')!=='credit'||$debt+$credit>(int)$policy['credit_limit_minor']))
            throw ValidationException::withMessages(['seller_balance'=>'البائع غير مؤهل حاليًا لتحصيل هذا الطلب عند الاستلام.']);
        if($account->balance_minor<0&&($policy['funding_mode']??'prepaid')==='prepaid')
            throw ValidationException::withMessages(['seller_balance'=>'يوجد رصيد مستحق على البائع يجب تسويته أولًا.']);
        StoreAccountHold::create(['account_id'=>$account->id,'store_sub_order_id'=>$subOrder->id,
            'balance_minor'=>$balance,'prepaid_minor'=>$prepaid,'credit_minor'=>$credit,'status'=>'active']);
    }
    public function release(StoreSubOrder $subOrder): void {
        StoreAccountHold::where('store_sub_order_id',$subOrder->id)->where('status','active')->update(['status'=>'released']);
    }
    public function recognizeCod(StoreSubOrder $subOrder): void {
        $hold=StoreAccountHold::where('store_sub_order_id',$subOrder->id)->lockForUpdate()->first();
        if(!$hold||$hold->status!=='active') return;
        $account=StoreSellerAccount::lockForUpdate()->findOrFail($hold->account_id);
        $total=$hold->balance_minor+$hold->prepaid_minor+$hold->credit_minor;
        $shipping=(int)($subOrder->financial_policy_snapshot['platform_shipping_due_minor']??0);
        $counter='clearing:cod_commission:'.$subOrder->id;
        $meta=['reference_type'=>'store_sub_order','reference_id'=>$subOrder->id];
        if($hold->balance_minor+$hold->credit_minor)
            $this->ledger->post($account,'balance_minor',-($hold->balance_minor+$hold->credit_minor),'cod:'.$subOrder->id.':balance','cod_obligation',$counter,$meta);
        if($hold->prepaid_minor)
            $this->ledger->post($account,'prepaid_minor',-$hold->prepaid_minor,'cod:'.$subOrder->id.':prepaid','cod_obligation',$counter,$meta);
        if($total||($subOrder->financial_policy_snapshot['platform_carrier_cost_minor']??0)) $this->ledger->journal('cod:'.$subOrder->id.':recognize','commission_recognized',$account->currency,[
            ['account_code'=>$counter,'amount_minor'=>$total],
            ['account_code'=>'income:commission','amount_minor'=>-((int)$subOrder->commission_minor-(int)$subOrder->commission_reversed_minor)],
            ...ShippingFinancials::allocation($subOrder->financial_policy_snapshot),
        ],$meta);
        $hold->update(['status'=>'consumed']);
        $subOrder->update(['commission_recognized_at'=>now()]);
    }
    public function allocatePaid(StoreSubOrder $subOrder): void {
        $snapshot=$subOrder->financial_policy_snapshot;
        $net=(int)$snapshot['seller_net_minor'];
        $account=$this->ledger->account($subOrder->company_id,$subOrder->order->currency);
        $this->ledger->post($account,'pending_minor',$net,'paid:'.$subOrder->id.':pending','sale_pending','clearing:customer_receipts',
            ['reference_type'=>'store_sub_order','reference_id'=>$subOrder->id]);
        $commission=(int)$subOrder->commission_minor;
        $platformShipping=(int)($snapshot['platform_shipping_due_minor']??0);
        if($commission||$platformShipping||($snapshot['platform_carrier_cost_minor']??0)) $this->ledger->journal('paid:'.$subOrder->id.':platform','sale_allocation',$account->currency,[
            ['account_code'=>'clearing:customer_receipts','amount_minor'=>$commission+$platformShipping],
            ['account_code'=>'liability:commission_deferred','amount_minor'=>-$commission],
            ...ShippingFinancials::allocation($snapshot),
        ],['sub_order_id'=>$subOrder->id]);
    }
    public function settle(StoreSubOrder $subOrder): void {
        if($subOrder->settled_at||!$subOrder->settlement_available_at||$subOrder->settlement_available_at->isFuture()) return;
        if ($subOrder->order->payment_method === 'cod') {
            if (!$subOrder->collected_at) return;
            if (($subOrder->financial_policy_snapshot['cod_beneficiary'] ?? '') === 'seller') {
                if (($subOrder->financial_policy_snapshot['commission_timing'] ?? '') === 'after_return_window') $this->recognizeCod($subOrder);
                $subOrder->update(['settled_at'=>now()]);
                return;
            }
            if (!$subOrder->remitted_at) return;
        }
        $account=$this->ledger->account($subOrder->company_id,$subOrder->order->currency);
        $net=(int)$subOrder->financial_policy_snapshot['seller_net_minor']-(int)$subOrder->seller_reversed_minor;
        if(!($subOrder->financial_policy_snapshot['automatic_debt_offset']??true)&&$account->balance_minor<0)return;
        $this->recognizePrepaidCommission($subOrder);
        if($net>0) {
            $this->ledger->post($account,'pending_minor',-$net,'settle:'.$subOrder->id.':pending','settlement_release','clearing:settlement');
            $this->ledger->post($account,'balance_minor',$net,'settle:'.$subOrder->id.':available','settlement_release','clearing:settlement');
        }
        $subOrder->update(['settled_at'=>now()]);
    }
    public function recognizePrepaidCommission(StoreSubOrder $subOrder): void {
        if($subOrder->commission_recognized_at) return;
        if(!$subOrder->delivered_at||!$subOrder->collected_at) return;
        $timing=$subOrder->financial_policy_snapshot['commission_timing']??'delivered_collected';
        if($timing==='remitted'&&!$subOrder->remitted_at&&$subOrder->shipment?->delivery_method!=='seller_local')return;
        if($timing==='after_return_window'&&(!$subOrder->settlement_available_at||$subOrder->settlement_available_at->isFuture()))return;
        $commission=(int)$subOrder->commission_minor-(int)$subOrder->commission_reversed_minor;
        if($commission) $this->ledger->journal('paid:'.$subOrder->id.':commission','commission_recognized',$subOrder->order->currency,[
            ['account_code'=>'liability:commission_deferred','amount_minor'=>$commission],
            ['account_code'=>'income:commission','amount_minor'=>-$commission],
        ],['sub_order_id'=>$subOrder->id]);
        $subOrder->update(['commission_recognized_at'=>now()]);
    }
}
