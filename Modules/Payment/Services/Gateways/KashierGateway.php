<?php
namespace Modules\Payment\Services\Gateways;
use App\Support\Money;
use Illuminate\Support\Facades\Http;
use Modules\Payment\Contracts\PaymentGatewayInterface;
use Modules\Payment\Models\PaymentTransaction;

class KashierGateway implements PaymentGatewayInterface {
    public function __construct(private array $config) {}
    private function base(): string { return ($this->config['_test_mode']??false)?'https://test-api.kashier.io':'https://api.kashier.io'; }
    private function http() { return Http::withHeaders(['Authorization'=>$this->config['secret_key']??'','api-key'=>$this->config['api_key']??''])->acceptJson()->connectTimeout(3)->timeout(9); }
    public function purchase(PaymentTransaction $transaction,array $options=[]): array {
        try {
            $response=$this->http()->post($this->base().'/v3/payment/sessions',[
                'expireAt'=>now('UTC')->addMinutes(30)->toIso8601String(),'maxFailureAttempts'=>3,'paymentType'=>'credit',
                'amount'=>(string)$transaction->amount,'currency'=>$transaction->currency,'order'=>'txn-'.$transaction->id,
                'merchantId'=>$this->config['merchant_id'],'merchantRedirect'=>$options['success_url']??route('api.payment.callback',['driver'=>'kashier']),
                'display'=>'ar','type'=>'one-time','allowedMethods'=>'card','manualCapture'=>false,'enable3DS'=>true,
                'serverWebhook'=>route('api.payment.webhook',['driver'=>'kashier']),
            ]);
            if($response->failed()||!$response->json('_id')||!$response->json('sessionUrl')) return ['success'=>false,'ambiguous'=>true,'error'=>'تعذر تأكيد إنشاء جلسة Kashier؛ يلزم الاستعلام قبل تكرارها.'];
            return ['success'=>true,'gateway_reference'=>(string)$response->json('_id'),'payment_url'=>$response->json('sessionUrl'),'expires_at'=>$response->json('expireAt')??now('UTC')->addMinutes(30)->toIso8601String()];
        } catch(\Illuminate\Http\Client\ConnectionException) { return ['success'=>false,'ambiguous'=>true,'error'=>'تعذر تأكيد نتيجة الاتصال ببوابة Kashier.']; }
    }
    private function orders(string $merchantOrder): array {
        $response=$this->http()->get($this->base().'/v3/payment/orders',['search'=>$merchantOrder,'limit'=>20,'page'=>1]);
        if($response->failed()||!is_array($response->json('data'))) throw new \RuntimeException('Kashier reconciliation unavailable.');
        return array_values(array_filter($response->json('data'),fn($order)=>($order['merchantOrderId']??'')===$merchantOrder
            &&($order['merchantId']??'')===($this->config['merchant_id']??'')
            &&isset($order['merchantDetails']['isLive'])&&(bool)$order['merchantDetails']['isLive']!==($this->config['_test_mode']??false)));
    }
    public function verify(array $data): array {
        $id=(string)($data['session_id']??'');
        if($id===''||$id!==($data['expected_reference']??null)||empty($data['expected_transaction_id'])||!isset($data['expected_amount_minor'],$data['expected_currency'])) return ['success'=>false];
        try {
            $response=$this->http()->get($this->base().'/v3/payment/sessions/'.rawurlencode($id).'/payment');
            if($response->failed())return ['success'=>false];$session=$response->json('data');$merchantOrder='txn-'.$data['expected_transaction_id'];
            if(!is_array($session)||($session['sessionId']??'')!==$id||($session['merchantId']??'')!==$this->config['merchant_id']
                ||($session['merchantOrderId']??'')!==$merchantOrder||Money::minor($session['amount']??'-1')!==$data['expected_amount_minor']
                ||strtoupper($session['currency']??'')!==$data['expected_currency']) return ['success'=>false,'error'=>'جلسة Kashier لا تطابق العملية.'];
            foreach($this->orders($merchantOrder) as $order) {
                if(($order['sessionId']??'')!==$id||Money::minor($order['order']['amount']??'-1')!==$data['expected_amount_minor']||strtoupper($order['order']['currency']??'')!==$data['expected_currency'])continue;
                foreach($order['transactions']??[] as $payment) {
                    if(!in_array(strtolower($payment['operation']??''),['pay','capture'],true)||($payment['status']??'')!=='SUCCESS'
                        ||empty($payment['transactionId'])||Money::minor($payment['amount']??'-1')!==$data['expected_amount_minor']
                        ||strtoupper($payment['currency']??'')!==$data['expected_currency']
                        ||(!empty($data['capture_reference'])&&$data['capture_reference']!==$payment['transactionId']))continue;
                    return ['success'=>true,'status'=>'completed','gateway_reference'=>$id,'payment_reference'=>$payment['transactionId'],'provider_order_reference'=>$order['orderId']];
                }
            }
            return ['success'=>true,'status'=>in_array($session['status']??'', ['EXPIRED','ABANDONED'],true)?'expired':'pending','gateway_reference'=>$id];
        } catch(\Throwable) { return ['success'=>false,'error'=>'تعذر الاستعلام عن معاملة Kashier.']; }
    }
    public function refund(PaymentTransaction $transaction,string|float $amount,array $options=[]): array {
        $orderId=$transaction->payload['provider_order_reference']??null;$minor=Money::minor($amount);
        if(!$orderId||!$transaction->gateway_payment_reference||$minor<=0||$minor>Money::minor($transaction->amount)-(int)$transaction->refunded_minor)return ['success'=>false];
        try {
            $base=($this->config['_test_mode']??false)?'https://test-fep.kashier.io':'https://fep.kashier.io';
            $response=$this->http()->put($base.'/v3/orders/'.rawurlencode($orderId),['apiOperation'=>'REFUND','reason'=>'Customer order refund',
                'transaction'=>['amount'=>Money::decimal($minor),'targetTransactionId'=>$transaction->gateway_payment_reference]]);
            $r=$response->json('response');
            if($response->failed()||!is_array($r)||Money::minor($r['amount']??'-1')!==$minor||strtoupper($r['currency']??'')!==$transaction->currency
                ||strtolower($r['operation']??'')!=='refund'||empty($r['transactionId']))return ['success'=>false,'ambiguous'=>true];
            return ['success'=>true,'status'=>($r['status']??'')==='SUCCESS'?'refunded':'pending','gateway_reference'=>$r['transactionId']];
        } catch(\Throwable) { return ['success'=>false,'ambiguous'=>true]; }
    }
    public function refundStatus(string $reference,PaymentTransaction $transaction,int $amount): array {
        $original=$transaction->payload['duplicate_of']??$transaction->id;
        try {
            foreach($this->orders('txn-'.$original) as $order) {
                if(($order['orderId']??'')!==($transaction->payload['provider_order_reference']??null))continue;
                foreach($order['transactions']??[] as $refund) {
                    if(($refund['transactionId']??'')!==$reference||!in_array(strtolower($refund['operation']??''),['refund','partial_refund'],true)
                        ||Money::minor($refund['amount']??'-1')!==$amount||strtoupper($refund['currency']??'')!==$transaction->currency
                        ||(isset($refund['targetedTransaction'])&&$refund['targetedTransaction']!==$transaction->gateway_payment_reference))continue;
                    return ['success'=>true,'status'=>($refund['status']??'')==='SUCCESS'?'refunded':'pending','gateway_reference'=>$reference];
                }
            }
        } catch(\Throwable) {}
        return ['success'=>false];
    }
}
