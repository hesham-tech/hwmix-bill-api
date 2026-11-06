<?php
namespace Modules\Payment\Services;
use App\Support\Money;
use Modules\Payment\Models\PaymentTransaction;
class KashierWebhookHandler {
    public function handle(array $request): array {
        $payload=$request['payload']??[];$data=$payload['data']??[];
        if(!is_array($data)||!preg_match('/^txn-([1-9][0-9]*)$/D',(string)($data['merchantOrderId']??''),$match))return ['success'=>false,'error'=>'مرجع إشعار Kashier غير صالح.'];
        $transaction=PaymentTransaction::withoutGlobalScopes()->where('gateway_driver','kashier')->find((int)$match[1]);
        if(!$transaction)return ['success'=>false,'error'=>'معاملة Kashier غير موجودة.'];
        $config=$transaction->gateway_config_snapshot?:PaymentGatewayFactory::configuration($transaction->gateway);
        if(!WebhookSignature::kashier($data,(string)($request['signature']??''),(string)($config['api_key']??'')))return ['success'=>false,'error'=>'توقيع Kashier غير صالح.'];
        if(!in_array($payload['event']??'', ['pay','capture'],true))return ['success'=>true,'ignored'=>true];
        if(Money::minor($data['amount']??'-1')!==Money::minor($transaction->amount)||strtoupper($data['currency']??'')!==strtoupper($transaction->currency))return ['success'=>false,'error'=>'قيمة إشعار Kashier لا تطابق العملية.'];
        $result=PaymentGatewayFactory::forTransaction($transaction)->verify([
            'session_id'=>(string)$transaction->gateway_reference,'expected_reference'=>(string)$transaction->gateway_reference,
            'expected_transaction_id'=>(string)$transaction->id,'expected_amount_minor'=>Money::minor($transaction->amount),
            'expected_currency'=>strtoupper($transaction->currency),'capture_reference'=>(string)$data['transactionId'],
        ]);
        if(!($result['success']??false))return $result;
        if(($result['provider_order_reference']??null)!==($data['kashierOrderId']??null)&&($result['status']??'')==='completed')return ['success'=>false,'error'=>'مرجع تحصيل Kashier لا يطابق الإشعار.'];
        return app(PaymentVerificationService::class)->apply($transaction,$result,'kashier:'.$data['transactionId'].':'.($data['status']??''));
    }
}
