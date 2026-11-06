<?php
namespace Modules\Payment\Actions;
use App\Support\Money;
use Illuminate\Support\Facades\DB;
use Modules\Core\Actions\BaseAction;
use Modules\Payment\Models\PaymentTransaction;
use Modules\Payment\Services\PaymentGatewayFactory;
use Modules\Payment\Services\WebhookSignature;
class HandleWebhookAction extends BaseAction {
    public function handle(array $data=[]): array {
        $name=strtolower($data['driver']??'');
        $handler=\Modules\Payment\Services\GatewayRegistry::definitions()[$name]['webhook_handler']??null;
        if($handler) return app($handler)->handle($data);
        $payload=$data['payload']??[];
        $object=$name==='stripe'?($payload['data']['object']??[]):($payload['obj']??[]);
        $reference=$name==='stripe'?($object['id']??null):($object['order']['id']??null);
        if($name==='stripe'&&(!is_string($payload['id']??null)||!str_starts_with($payload['id'],'evt_'))) return ['success'=>false,'error'=>'معرف إشعار Stripe غير صالح.'];
        if(!in_array($name,['stripe','paymob'],true)||!is_scalar($reference)||!$reference)
            return ['success'=>false,'error'=>'إشعار دفع غير صالح.'];
        $candidates=PaymentTransaction::withoutGlobalScopes()->where(function($q) use($reference,$name,$object) {
            $q->where('gateway_reference',(string)$reference);
            if($name==='stripe'&&ctype_digit((string)($object['client_reference_id']??'')))
                $q->orWhere(fn($query)=>$query->whereKey((int)$object['client_reference_id'])->whereNull('gateway_reference'));
        })
            ->where(fn($q)=>$q->where('gateway_driver',$name)->orWhereNull('gateway_driver'))->get();
        $transaction=null;
        foreach($candidates as $candidate) {
            $gateway=$candidate->gateway;
            if(($candidate->gateway_driver?:$gateway?->driver)!==$name) continue;
            $config=$candidate->gateway_config_snapshot?:($gateway?PaymentGatewayFactory::configuration($gateway):[]);
            $valid=$name==='stripe'
                ?WebhookSignature::stripe($data['raw_body']??'',$data['signature']??'',$config['webhook_secret']??'')
                :WebhookSignature::paymob($object,$data['signature']??'',$config['hmac_secret']??'');
            if($valid) { $transaction=$candidate; break; }
        }
        if(!$transaction) return ['success'=>false,'error'=>'تعذر توثيق إشعار الدفع.'];
        if ($name === 'paymob' && ((string) ($object['amount_cents'] ?? '') !== (string) Money::minor($transaction->amount)
            || strtoupper($object['currency'] ?? '') !== strtoupper($transaction->currency))) {
            return ['success'=>false,'error'=>'مبلغ الإشعار أو عملته لا يطابق العملية.'];
        }
        $result=PaymentGatewayFactory::forTransaction($transaction)->verify([
            'capture_reference'=>$name==='paymob'?($object['id']??null):null,
            'expected_merchant_reference'=>$transaction->idempotency_key?'txn-'.$transaction->id.'-'.$transaction->idempotency_key:null,
            'session_id'=>$reference,'order'=>$reference,'expected_reference'=>(string)($transaction->gateway_reference?:$reference),
            'expected_transaction_id'=>(string)$transaction->id,'expected_amount_minor'=>Money::minor($transaction->amount),
            'expected_currency'=>strtoupper($transaction->currency),
        ]);
        if(!($result['success']??false)) return $result;
        if ($name === 'paymob') {
            $result['payment_reference'] = (string) $object['id'];
            $completed = ($object['success'] ?? false) === true && ($object['pending'] ?? true) === false
                && ($object['error_occured'] ?? true) === false && ($object['is_voided'] ?? true) === false
                && ($object['is_refunded'] ?? true) === false
                && (!(bool) ($object['is_auth'] ?? false) || (bool) ($object['is_capture'] ?? false));
            if (!$completed) $result['status'] = 'pending';
        }
        $status=$result['status']??'pending';
        $providerEvent=$name==='stripe'?($payload['id']??''):(($object['id']??'').':'.$status);
        return app(\Modules\Payment\Services\PaymentVerificationService::class)->apply($transaction,$result,(string)$providerEvent);
    }
}
