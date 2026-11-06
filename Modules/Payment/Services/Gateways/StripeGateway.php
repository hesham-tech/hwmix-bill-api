<?php
namespace Modules\Payment\Services\Gateways;
use App\Support\Money;
use Illuminate\Support\Facades\Http;
use Modules\Payment\Contracts\PaymentGatewayInterface;
use Modules\Payment\Models\PaymentTransaction;

class StripeGateway implements PaymentGatewayInterface
{
    public function __construct(protected array $config) {}
    private function http() {
        return Http::withToken($this->config['secret_key']??'')->connectTimeout(4)->timeout(12);
    }
    public function purchase(PaymentTransaction $transaction,array $options=[]): array {
        try {
            $response=$this->http()->asForm()->withHeaders(['Idempotency-Key'=>'payment-'.($transaction->idempotency_key?:$transaction->id)])
                ->post('https://api.stripe.com/v1/checkout/sessions',[
                    'payment_method_types'=>['card'],
                    'line_items'=>[['price_data'=>['currency'=>strtolower($transaction->currency),
                        'product_data'=>['name'=>'Payment #'.$transaction->id],
                        'unit_amount'=>Money::minor($transaction->amount)],'quantity'=>1]],
                    'mode'=>'payment','client_reference_id'=>(string)$transaction->id,
                    'success_url'=>$options['success_url']??route('api.payment.callback',['driver'=>'stripe']),
                    'cancel_url'=>$options['cancel_url']??route('api.payment.callback',['driver'=>'stripe']),
                ]);
            if($response->failed()||!$response->json('id')||!$response->json('url'))
                return ['success'=>false,'error'=>'تعذر إنشاء جلسة الدفع.','retryable'=>true];
            return ['success'=>true,'payment_url'=>$response->json('url'),'gateway_reference'=>$response->json('id'),'expires_at'=>$response->json('expires_at')??now('UTC')->addDay()->toIso8601String()];
        } catch(\Illuminate\Http\Client\ConnectionException) {
            return ['success'=>false,'error'=>'تعذر الاتصال ببوابة الدفع.','retryable'=>true];
        }
    }
    public function verify(array $data): array {
        if(empty($data['expected_reference'])||empty($data['expected_transaction_id'])||!isset($data['expected_amount_minor'],$data['expected_currency']))
            return ['success'=>false,'error'=>'بيانات المطابقة المالية غير مكتملة.'];
        $id=(string)($data['session_id']??'');
        if($id!==$data['expected_reference']||!preg_match('/^cs_[a-zA-Z0-9_]+$/D',$id))
            return ['success'=>false,'error'=>'مرجع الدفع لا يطابق العملية.'];
        try {
            $response=$this->http()->get('https://api.stripe.com/v1/checkout/sessions/'.rawurlencode($id));
            if($response->failed()) return ['success'=>false,'error'=>'تعذر الاستعلام عن الدفع.'];
            $session=$response->json();
            if(($session['id']??null)!==$id
                ||(string)($session['client_reference_id']??'')!==$data['expected_transaction_id']
                ||($session['amount_total']??null)!==$data['expected_amount_minor']
                ||strtoupper($session['currency']??'')!==$data['expected_currency']
                ||!array_key_exists('livemode',$session)
                ||(bool)$session['livemode']===(bool)($this->config['_test_mode']??false))
                return ['success'=>false,'error'=>'بيانات الدفع المستلمة لا تطابق المعاملة.'];
            return ['success'=>true,'status'=>($session['payment_status']??'')==='paid'?'completed':(($session['status']??'')==='expired'?'expired':'pending'),
                'payment_reference'=>$session['payment_intent']??null,'gateway_reference'=>$id];
        } catch(\Illuminate\Http\Client\ConnectionException) {
            return ['success'=>false,'error'=>'تعذر التحقق من الدفع؛ ستتم إعادة المحاولة.'];
        }
    }
    public function refund(PaymentTransaction $transaction,string|float $amount,array $options=[]): array {
        if(!$transaction->gateway_payment_reference||!str_starts_with($transaction->gateway_payment_reference,'pi_'))
            return ['success'=>false,'error'=>'مرجع التحصيل غير متاح للاسترداد.'];
        $minor=Money::minor($amount);
        if($minor<=0||$minor>Money::minor($transaction->amount)-(int)$transaction->refunded_minor)
            return ['success'=>false,'error'=>'قيمة الاسترداد غير صالحة.'];
        try {
            $response=$this->http()->asForm()->withHeaders(['Idempotency-Key'=>$options['idempotency_key']??('refund-'.$transaction->id.'-'.$transaction->refunded_minor.'-'.$minor)])
                ->post('https://api.stripe.com/v1/refunds',['payment_intent'=>$transaction->gateway_payment_reference,'amount'=>$minor]);
            if($response->failed()||!$response->json('id')) return ['success'=>false,'error'=>'تعذر تنفيذ الاسترداد.'];
            return ['success'=>true,'status'=>$response->json('status')==='succeeded'?'refunded':'pending',
                'gateway_reference'=>$response->json('id')];
        } catch(\Illuminate\Http\Client\ConnectionException) {
            return ['success'=>false,'error'=>'تعذر تأكيد الاسترداد؛ يلزم الاستعلام قبل أي إعادة.','ambiguous'=>true];
        }
    }
    public function refundStatus(string $reference,PaymentTransaction $transaction,int $amount): array {
        if(!preg_match('/^re_[a-zA-Z0-9]+$/D',$reference)) return ['success'=>false];
        $response=$this->http()->get('https://api.stripe.com/v1/refunds/'.rawurlencode($reference));
        if($response->failed()||$response->json('id')!==$reference||$response->json('amount')!==$amount
            ||strtoupper((string)$response->json('currency'))!==strtoupper($transaction->currency)
            ||$response->json('payment_intent')!==$transaction->gateway_payment_reference) return ['success'=>false];
        return ['success'=>true,'status'=>$response->json('status')==='succeeded'?'refunded':'pending','gateway_reference'=>$reference];
    }
}
