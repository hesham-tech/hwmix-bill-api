<?php
namespace Modules\Payment\Services\Gateways;
use App\Support\Money;
use Illuminate\Support\Facades\Http;
use Modules\Payment\Contracts\PaymentGatewayInterface;
use Modules\Payment\Models\PaymentTransaction;

class PaymobGateway implements PaymentGatewayInterface
{
    public function __construct(protected array $config) {}
    private function http() { return Http::connectTimeout(3)->timeout(7); }
    private function token(): ?string {
        $response=$this->http()->post('https://accept.paymob.com/api/auth/tokens',['api_key'=>$this->config['api_key']??'']);
        return $response->successful()?$response->json('token'):null;
    }
    public function purchase(PaymentTransaction $transaction,array $options=[]): array {
        $billing=$options['billing_data']??[];
        if(empty($billing['first_name'])||empty($billing['phone_number']))
            return ['success'=>false,'error'=>'بيانات صاحب الطلب مطلوبة للدفع.'];
        try {
            $token=$this->token();
            if(!$token) return ['success'=>false,'error'=>'تعذر توثيق حساب الدفع.'];
            $orderId=$transaction->gateway_reference;
            if(!$orderId) {
                $response=$this->http()->post('https://accept.paymob.com/api/ecommerce/orders',[
                    'auth_token'=>$token,'delivery_needed'=>'false','amount_cents'=>Money::minor($transaction->amount),
                    'currency'=>strtoupper($transaction->currency),
                    'merchant_order_id'=>'txn-'.$transaction->id.'-'.$transaction->idempotency_key,
                ]);
                if($response->failed()||!$response->json('id')) return ['success'=>false,'error'=>'تعذر تسجيل طلب الدفع.'];
                $orderId=(string)$response->json('id');
                $transaction->forceFill(['gateway_reference'=>$orderId])->saveQuietly();
            }
            $billing=array_merge(array_fill_keys(['last_name','email','apartment','floor','street','building',
                'shipping_method','postal_code','city','state'],'NA'),['country'=>'EG'],$billing);
            $response=$this->http()->post('https://accept.paymob.com/api/acceptance/payment_keys',[
                'auth_token'=>$token,'amount_cents'=>Money::minor($transaction->amount),'expiration'=>3600,
                'order_id'=>$orderId,'billing_data'=>$billing,'currency'=>strtoupper($transaction->currency),
                'integration_id'=>(int)($this->config['integration_id']??0),
            ]);
            if($response->failed()||!$response->json('token')) return ['success'=>false,'error'=>'تعذر إنشاء صفحة الدفع.'];
            return ['success'=>true,'gateway_reference'=>$orderId,
                'expires_at'=>now('UTC')->addHour()->toIso8601String(),
                'payment_url'=>'https://accept.paymob.com/api/acceptance/iframes/'.rawurlencode($this->config['iframe_id']).'?payment_token='.rawurlencode($response->json('token'))];
        } catch(\Illuminate\Http\Client\ConnectionException) {
            return ['success'=>false,'error'=>'تعذر تأكيد إنشاء الدفع؛ يلزم الاستعلام قبل إعادة الإنشاء.','ambiguous'=>true];
        }
    }
    public function verify(array $data): array {
        if(empty($data['expected_reference'])||!isset($data['expected_amount_minor'],$data['expected_currency']))
            return ['success'=>false,'error'=>'بيانات المطابقة المالية غير مكتملة.'];
        $id=(string)($data['order']??'');
        if($id!==$data['expected_reference']||!ctype_digit($id))
            return ['success'=>false,'error'=>'مرجع الدفع لا يطابق العملية.'];
        try {
            $token=$this->token();
            if(!$token) return ['success'=>false,'error'=>'تعذر توثيق حساب الدفع.'];
            $response=$this->http()->withToken($token)->get('https://accept.paymob.com/api/ecommerce/orders/'.rawurlencode($id));
            if($response->failed()) return ['success'=>false,'error'=>'تعذر الاستعلام عن الدفع.'];
            $order=$response->json();
            if((string)($order['id']??'')!==$id||($order['amount_cents']??null)!==$data['expected_amount_minor']
                ||strtoupper($order['currency']??'')!==$data['expected_currency'])
                return ['success'=>false,'error'=>'بيانات الدفع المستلمة لا تطابق المعاملة.'];
            if(!empty($data['expected_merchant_reference'])&&($order['merchant_order_id']??null)!==$data['expected_merchant_reference'])
                return ['success'=>false,'error'=>'مرجع التاجر لا يطابق محاولة الدفع.'];
            $paid=($order['is_paid']??false)===true
                ||(isset($order['paid_amount_cents'])&&(int)$order['paid_amount_cents']>=$data['expected_amount_minor']);
            if(!$paid) return ['success'=>true,'status'=>'pending','gateway_reference'=>$id];
            $capture=$data['capture_reference']??null;
            $inquiry=$capture
                ?$this->http()->withToken($token)->get('https://accept.paymob.com/api/acceptance/transactions/'.rawurlencode((string)$capture))
                :$this->http()->post('https://accept.paymob.com/api/ecommerce/orders/transaction_inquiry',['auth_token'=>$token,'order_id'=>$id]);
            if($inquiry->failed()) return ['success'=>false,'error'=>'تعذر مطابقة معاملة التحصيل.'];
            $payment=$inquiry->json();
            if((string)($payment['order']['id']??'')!==$id||(string)($payment['amount_cents']??'')!==(string)$data['expected_amount_minor']
                ||strtoupper($payment['currency']??'')!==$data['expected_currency']
                ||(string)($payment['integration_id']??'')!==(string)($this->config['integration_id']??'')
                ||(isset($payment['is_live'])&&(bool)$payment['is_live']===(bool)($this->config['_test_mode']??false))
                ||($capture&&(string)($payment['id']??'')!==(string)$capture)) return ['success'=>false,'error'=>'معاملة التحصيل لا تطابق الطلب أو حساب التكامل.'];
            $complete=($payment['success']??false)===true&&($payment['pending']??true)===false&&($payment['error_occured']??false)===false
                &&($payment['is_voided']??true)===false&&($payment['is_refunded']??true)===false&&($payment['is_refund']??false)===false
                &&(!(bool)($payment['is_auth']??false)||(bool)($payment['is_capture']??false));
            return ['success'=>true,'status'=>$complete?'completed':'pending','gateway_reference'=>$id,'payment_reference'=>(string)($payment['id']??'')];
        } catch(\Illuminate\Http\Client\ConnectionException) {
            return ['success'=>false,'error'=>'تعذر التحقق من الدفع؛ ستتم إعادة المحاولة.'];
        }
    }
    public function refund(PaymentTransaction $transaction,string|float $amount,array $options=[]): array {
        if(!$transaction->gateway_payment_reference||!ctype_digit((string)$transaction->gateway_payment_reference))
            return ['success'=>false,'error'=>'مرجع التحصيل غير متاح للاسترداد.'];
        $minor=Money::minor($amount);
        if($minor<=0||$minor>Money::minor($transaction->amount)-(int)$transaction->refunded_minor)
            return ['success'=>false,'error'=>'قيمة الاسترداد غير صالحة.'];
        try {
            $token=$this->token();
            if(!$token) return ['success'=>false,'error'=>'تعذر توثيق حساب الدفع.'];
            $response=$this->http()->post('https://accept.paymob.com/api/acceptance/void_refund/refund',[
                'auth_token'=>$token,'transaction_id'=>$transaction->gateway_payment_reference,'amount_cents'=>$minor]);
            if($response->failed()||!$response->json('id')) return ['success'=>false,'error'=>'تعذر تأكيد الاسترداد؛ يلزم الاستعلام قبل إعادة الإرسال.','ambiguous'=>true];
            return ['success'=>true,'status'=>$response->json('success')===true?'refunded':'pending',
                'gateway_reference'=>(string)$response->json('id')];
        } catch(\Illuminate\Http\Client\ConnectionException) {
            return ['success'=>false,'error'=>'تعذر تأكيد الاسترداد؛ يلزم الاستعلام قبل إعادة الإرسال.','ambiguous'=>true];
        }
    }
    public function refundStatus(string $reference,PaymentTransaction $transaction,int $amount): array {
        if(!ctype_digit($reference)) return ['success'=>false];
        $token=$this->token();if(!$token)return ['success'=>false];
        $response=$this->http()->withToken($token)->get('https://accept.paymob.com/api/acceptance/transactions/'.$reference);
        if($response->failed())return ['success'=>false];$r=$response->json();
        $parent=is_array($r['parent_transaction']??null)?($r['parent_transaction']['id']??null):($r['parent_transaction']??null);
        if((string)($r['id']??'')!==$reference||(string)$parent!==(string)$transaction->gateway_payment_reference
            ||!($r['is_refund']??false)||(int)($r['amount_cents']??-1)!==$amount||strtoupper($r['currency']??'')!==strtoupper($transaction->currency)) return ['success'=>false];
        return ['success'=>true,'status'=>($r['success']??false)===true&&($r['pending']??true)===false?'refunded':'pending','gateway_reference'=>$reference];
    }
}
