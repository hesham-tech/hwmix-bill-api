<?php
namespace Modules\Store\Services;
use App\Support\Money;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Payment\Models\PaymentTransaction;
use Modules\Store\Models\{StoreOrder,StoreSubOrder,StoreRefund,StoreGatewaySettlement};
class StoreGatewaySettlementService {
    public function remaining(PaymentTransaction $payment,bool $lock=false): int {
        if(!$payment->gateway_payment_reference||!$payment->verified_at)return 0;
        $refunds=StoreRefund::where('payment_transaction_id',$payment->id)->where('status','completed');
        if($lock)$refunds->lockForUpdate();$ids=$refunds->pluck('id');
        $proofs=DB::table('store_payment_proofs')->where('owner_type',(new StoreRefund)->getMorphClass())->whereIn('owner_id',$ids)->where('scope','like','gateway:%');
        if($lock)$proofs->lockForUpdate();
        $refunded='0';foreach($proofs->get(['amount_minor']) as $proof)$refunded=bcadd($refunded,(string)$proof->amount_minor,0);
        $items=DB::table('store_gateway_settlement_items')->where('payment_transaction_id',$payment->id);if($lock)$items->lockForUpdate();
        $settled='0';foreach($items->get(['gross_minor']) as $item)$settled=bcadd($settled,(string)$item->gross_minor,0);
        return Money::minor(bcsub(bcsub((string)Money::minor($payment->amount),$refunded,0),$settled,0),0);
    }
    private function identity(PaymentTransaction $payment): string {
        $config=$payment->gateway_config_snapshot??[];
        return hash('sha256',json_encode([$payment->gateway_driver,$payment->payment_gateway_id,$payment->currency,
            $config['_test_mode']??false,$config['merchant_id']??$config['account_id']??null],JSON_THROW_ON_ERROR));
    }
    public function record(array $data): StoreGatewaySettlement {
        if(!filter_var($data['confirmed']??false,FILTER_VALIDATE_BOOLEAN))throw ValidationException::withMessages(['confirmed'=>'أكد مطابقة كشف المزود والتحويل البنكي الفعلي.']);
        $ids=array_values(array_unique(array_map('intval',$data['payment_ids'])));sort($ids);
        $net=Money::minor((string)$data['net_amount']);$fee=Money::minor((string)$data['fee']);$tax=Money::minor((string)($data['recoverable_tax']??'0'));
        if(!$ids||count($ids)>100||$fee<0||$tax<0||$tax>$fee)throw ValidationException::withMessages(['settlement'=>'بيانات مبالغ التسوية غير صحيحة.']);
        $reference=trim($data['reference']);$referenceHash=hash('sha256',mb_strtoupper(preg_replace('/\s+/u','',$reference)));
        $fingerprint=hash('sha256',json_encode([$ids,$net,$fee,$tax,$referenceHash],JSON_THROW_ON_ERROR));
        return DB::transaction(function()use($data,$ids,$net,$fee,$tax,$reference,$referenceHash,$fingerprint){
            $existing=StoreGatewaySettlement::where('operation_key',$data['operation_key'])->lockForUpdate()->first();
            if($existing){if(!hash_equals($existing->fingerprint,$fingerprint))throw ValidationException::withMessages(['operation_key'=>'مفتاح العملية مرتبط بتسوية أخرى.']);return $existing;}
            $payments=PaymentTransaction::withoutGlobalScopes()->whereIn('id',$ids)->where('payable_type',StoreOrder::class)
                ->where('company_id',config('app.master_company_id',1))->orderBy('id')->lockForUpdate()->get();
            if($payments->count()!==count($ids))throw ValidationException::withMessages(['payment_ids'=>'اختر محاولات دفع متجر المنصة فقط.']);
            $identity=$this->identity($payments->first());$currency=$payments->first()->currency;
            $duplicate=StoreGatewaySettlement::where('account_identity',$identity)->where('reference_hash',$referenceHash)->lockForUpdate()->first();
            if($duplicate){if(!hash_equals($duplicate->fingerprint,$fingerprint))throw ValidationException::withMessages(['reference'=>'سبق قيد كشف المزود بهذا المرجع وببيانات مختلفة.']);return $duplicate;}
            $gross='0';$allocations=[];
            foreach($payments as $payment){
                if($this->identity($payment)!==$identity||!$payment->gateway_payment_reference||!$payment->verified_at)
                    throw ValidationException::withMessages(['payment_ids'=>'يجب أن تكون العمليات محصلة فعليًا ومن حساب مزود واحد والعملة وبيئة التشغيل نفسيهما.']);
                $due=$this->remaining($payment,true);if(!$due)throw ValidationException::withMessages(['payment_ids'=>'إحدى العمليات لا تحتوي مبلغًا جديدًا للمطابقة.']);
                $allocations[$payment->id]=$due;$gross=bcadd($gross,(string)$due,0);
            }
            $gross=Money::minor($gross,0);
            if(bcsub((string)$gross,(string)$fee,0)!==(string)$net)throw ValidationException::withMessages(['net_amount'=>'الصافي والرسوم لا يطابقان المستحق غير المسوى. أضف عمليات الاسترداد السابقة عند خصمها من الدفعة.']);
            $settlement=StoreGatewaySettlement::create(['operation_key'=>$data['operation_key'],'fingerprint'=>$fingerprint,'account_identity'=>$identity,
                'reference_hash'=>$referenceHash,'reference'=>$reference,'gross_minor'=>$gross,'net_minor'=>$net,'fee_minor'=>$fee,'recoverable_tax_minor'=>$tax,'currency'=>$currency,'recorded_by'=>auth()->id()]);
            if($net)app(StorePaymentProofService::class)->claim($net>0?'manual:incoming:platform':'manual:platform',$reference,$settlement,abs($net),$currency);
            foreach($allocations as $id=>$amount)DB::table('store_gateway_settlement_items')->insert(['settlement_id'=>$settlement->id,'payment_transaction_id'=>$id,'gross_minor'=>$amount]);
            app(StoreLedgerService::class)->journal('gateway:settlement:'.$settlement->id,'gateway_bank_settlement',$currency,[
                ['account_code'=>'asset:bank_clearing','amount_minor'=>$net],['account_code'=>'expense:gateway_fees','amount_minor'=>$fee-$tax],
                ['account_code'=>'asset:recoverable_gateway_tax','amount_minor'=>$tax],['account_code'=>'asset:gateway_receivable','amount_minor'=>-$gross],
            ],['settlement_id'=>$settlement->id,'reference'=>$reference,'actor_id'=>auth()->id(),'payment_ids'=>$ids]);
            $primaryOrders=$payments->filter(fn($p)=>empty($p->payload['duplicate_of']))->pluck('payable_id')->unique()->values();
            StoreOrder::whereIn('id',$primaryOrders)->orderBy('id')->lockForUpdate()->get();
            foreach(StoreSubOrder::whereIn('store_order_id',$primaryOrders)->where('status','!=','cancelled')->whereNotNull('collected_at')->orderBy('store_order_id')->orderBy('id')->lockForUpdate()->get() as $sub){
                if(!$sub->remitted_at)$sub->update(['remitted_at'=>now('UTC')]);
                app(SellerFinanceService::class)->recognizePrepaidCommission($sub);
            }
            return $settlement;
        },3);
    }
}
