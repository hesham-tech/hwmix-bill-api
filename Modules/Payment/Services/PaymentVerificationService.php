<?php
namespace Modules\Payment\Services;
use App\Support\Money;
use Illuminate\Support\Facades\DB;
use Modules\Payment\Models\PaymentTransaction;

class PaymentVerificationService {
    public function verify(PaymentTransaction $transaction,array $extra=[]): array {
        $reference=(string)($transaction->gateway_reference?:($extra['reference']??''));
        if($reference==='') return ['success'=>false,'error'=>'مرجع البوابة غير متاح للمطابقة.'];
        $result=PaymentGatewayFactory::forTransaction($transaction)->verify(array_merge($extra,[
            'session_id'=>$reference,'order'=>$reference,'expected_reference'=>$reference,
            'expected_transaction_id'=>(string)$transaction->id,'expected_amount_minor'=>Money::minor($transaction->amount),'expected_currency'=>strtoupper($transaction->currency),
            'expected_merchant_reference'=>$transaction->idempotency_key?'txn-'.$transaction->id.'-'.$transaction->idempotency_key:null,
        ]));
        if(!($result['success']??false)) return $result;
        return $this->apply($transaction,$result,'inquiry:'.hash('sha256',json_encode([$transaction->id,$result])));
    }
    /** Only authoritative provider responses may enter this method. */
    public function apply(PaymentTransaction $transaction,array $result,string $event): array {
        return DB::transaction(function() use($transaction,$result,$event) {
            $locked=PaymentTransaction::withoutGlobalScopes()->lockForUpdate()->findOrFail($transaction->id);
            $status=$result['status']??'pending';$capture=(string)($result['payment_reference']??'');
            $eventKey=hash('sha256',$locked->id.':'.$event);
            if(DB::table('payment_webhook_events')->where('event_key',$eventKey)->exists()) return ['success'=>true,'status'=>$locked->status,'duplicate'=>true];
            if($status==='completed'&&$capture==='') return ['success'=>false,'error'=>'التحصيل يحتاج مرجع معاملة موثقًا قبل التنفيذ.'];
            DB::table('payment_webhook_events')->insert(['payment_transaction_id'=>$locked->id,'event_key'=>$eventKey,'status'=>$status,'created_at'=>now('UTC'),'updated_at'=>now('UTC')]);
            if(in_array($locked->status,['completed','refunded','partially_refunded'],true)) {
                if($status==='completed'&&$capture!==$locked->gateway_payment_reference) $this->excessCapture($locked,$capture);
                return ['success'=>true,'status'=>$locked->status];
            }
            $locked->update(['status'=>$status,'gateway_reference'=>$result['gateway_reference']??$locked->gateway_reference,
                'payload'=>array_replace($locked->payload??[],array_intersect_key($result,['provider_order_reference'=>true])),
                'verified_at'=>$status==='completed'?now('UTC'):null,'gateway_payment_reference'=>$capture?:$locked->gateway_payment_reference]);
            if($status==='completed') {
                $payable=$locked->payable;
                if(!$payable||!method_exists($payable,'markAsPaid')) throw new \LogicException('Verified payment has no supported payable.');
                $payable->markAsPaid($locked);
            }
            return ['success'=>true,'status'=>$locked->status];
        },3);
    }
    private function excessCapture(PaymentTransaction $original,string $capture): void {
        $key=hash('sha256','excess:'.$original->payment_gateway_id.':'.$original->gateway_driver.':'.$capture);
        if(PaymentTransaction::withoutGlobalScopes()->where('idempotency_key',$key)->exists()) return;
        $extra=PaymentTransaction::create(['payment_gateway_id'=>$original->payment_gateway_id,'payable_type'=>$original->payable_type,'payable_id'=>$original->payable_id,
            'amount'=>$original->amount,'currency'=>$original->currency,'status'=>'completed','company_id'=>$original->company_id,'branch_id'=>null,
            'idempotency_key'=>$key,'gateway_driver'=>$original->gateway_driver,'gateway_config_snapshot'=>$original->gateway_config_snapshot,
            'gateway_test_mode'=>$original->gateway_test_mode,'gateway_reference'=>$original->gateway_reference,'gateway_payment_reference'=>$capture,
            'verified_at'=>now('UTC'),'payload'=>array_replace(array_intersect_key($original->payload??[],['provider_order_reference'=>true]),['duplicate_of'=>$original->id])]);
        if($original->payable_type===\Modules\Store\Models\StoreOrder::class) {
            app(\Modules\Store\Services\StoreRefundService::class)->excessPayment($original->payable,$extra);
        } else {
            $extra->update(['status'=>'reconciliation_needed']);
            \Illuminate\Support\Facades\Log::warning('Additional payment capture requires reconciliation',['transaction_id'=>$extra->id,'original_transaction_id'=>$original->id]);
        }
    }
}
