<?php
namespace Modules\Payment\Services;

use App\Support\Money;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Payment\Models\PaymentGateway;
use Modules\Payment\Models\PaymentTransaction;

class PaymentSessionService
{
    public function resume(PaymentTransaction $transaction,array $options=[]): array {
        $transaction->refresh();
        if(in_array($transaction->status,['completed','partially_refunded','refunded'],true)) return ['transaction_id'=>$transaction->id,'status'=>'completed','payment_url'=>null];
        if(in_array($transaction->status,['failed','cancelled','expired'],true)) throw ValidationException::withMessages(['payment'=>'انتهت هذه المحاولة. حدّث الطلب لبدء محاولة جديدة.']);
        $payload=$transaction->payload??[];$url=$payload['payment_url']??null;
        $expired=!empty($payload['payment_url_expires_at'])&&\Carbon\Carbon::parse($payload['payment_url_expires_at'])->isPast();
        if($url&&!$expired) return ['transaction_id'=>$transaction->id,'status'=>$transaction->status,'payment_url'=>Crypt::decryptString($url)];
        if($transaction->gateway_driver==='paymob'&&$transaction->gateway_reference) {
            $lock=\Illuminate\Support\Facades\Cache::lock('payment-session-renew:'.$transaction->id,60);
            if(!$lock->get()) throw ValidationException::withMessages(['payment'=>'جارٍ تجهيز جلسة الدفع. أعد المحاولة بعد لحظات.']);
            try {return $this->finishAttempt($transaction,PaymentGatewayFactory::forTransaction($transaction)->purchase($transaction,$options));}
            finally {$lock->release();}
        }
        if($transaction->gateway_reference) {
            $result=app(PaymentVerificationService::class)->verify($transaction);
            if(($result['status']??'')==='completed') return ['transaction_id'=>$transaction->id,'status'=>'completed','payment_url'=>null];
        }
        throw ValidationException::withMessages(['payment'=>'المحاولة بحاجة إلى التحقق من البوابة. حدّث الطلب قبل إعادة الدفع.']);
    }
    /** Called only with server-authorized models and server-calculated amounts. */
    public function start(Model $payable,PaymentGateway $gateway,string $amount,string $currency,string $key,array $options=[]): array
    {
        $config=PaymentGatewayFactory::configuration($gateway);
        if (app()->environment('production') && $gateway->is_test_mode) throw ValidationException::withMessages(['payment_gateway_id'=>'حساب الاختبار لا يقبل مدفوعات المتجر الإنتاجية.']);
        if(!$gateway->is_active||!GatewayRegistry::ready($gateway->driver,$config))
            throw ValidationException::withMessages(['payment_gateway_id'=>'حساب الدفع غير جاهز.']);
        if(Money::minor($amount)<=0) throw ValidationException::withMessages(['amount'=>'قيمة الدفع غير صالحة.']);
        if(!preg_match('/^[a-zA-Z0-9_-]{16,64}$/D',$key))
            throw ValidationException::withMessages(['idempotency_key'=>'مفتاح محاولة الدفع غير صالح.']);
        $currency=strtoupper($currency);
        if(!in_array($currency,['EGP','USD','EUR','GBP','SAR','AED'],true))
            throw ValidationException::withMessages(['currency'=>'عملة الدفع غير مدعومة في هذا الموصل.']);
        [$transaction,$created]=DB::transaction(function() use($payable,$gateway,$amount,$currency,$key,$config) {
            $lockedPayable=$payable::withoutGlobalScopes()->lockForUpdate()->findOrFail($payable->getKey());
            if (($lockedPayable instanceof \Modules\Store\Models\StoreOrder && ($lockedPayable->status==='cancelled'||$lockedPayable->payment_status==='paid'||$lockedPayable->hold_expires_at?->isPast()))
                || ($lockedPayable instanceof \App\Models\CompanySubscription && $lockedPayable->status!=='pending')) {
                throw ValidationException::withMessages(['payment'=>'تغيرت حالة الطلب أو الاشتراك قبل بدء الدفع.']);
            }
            $existing=PaymentTransaction::withoutGlobalScopes()->where('idempotency_key',$key)->first();
            if($existing) {
                if($existing->payable_type!==$payable->getMorphClass()||(string)$existing->payable_id!==(string)$payable->getKey()
                    ||Money::minor($existing->amount)!==Money::minor($amount)||$existing->currency!==$currency)
                    throw ValidationException::withMessages(['idempotency_key'=>'هذا المفتاح مرتبط بمحاولة مختلفة.']);
                return [$existing,false];
            }
            $existing=PaymentTransaction::withoutGlobalScopes()->where('payable_type',$payable->getMorphClass())
                ->where('payable_id',$payable->getKey())->whereNotIn('status',['failed','cancelled','expired'])->latest('id')->first();
            if($existing) return [$existing,false];
            return [PaymentTransaction::create([
                'payment_gateway_id'=>$gateway->id,'payable_type'=>$payable->getMorphClass(),'payable_id'=>$payable->getKey(),
                'amount'=>Money::decimal(Money::minor($amount)),'currency'=>$currency,'status'=>'initiating',
                'company_id'=>$gateway->company_id,'branch_id'=>null,'created_by'=>auth()->id(),
                'idempotency_key'=>$key,'gateway_driver'=>$gateway->driver,'gateway_config_snapshot'=>$config,
                'gateway_test_mode'=>(bool)$gateway->is_test_mode,
            ]),true];
        },3);
        if(!$created) {
            return $this->resume($transaction,$options);
        }
        // The durable attempt is committed before the network call: a timeout must not erase its audit trail.
        $result=PaymentGatewayFactory::forTransaction($transaction)->purchase($transaction,$options);
        return $this->finishAttempt($transaction,$result);
    }
    private function finishAttempt(PaymentTransaction $transaction,array $result): array {
        $transaction->refresh();
        if(in_array($transaction->status,['completed','partially_refunded','refunded'],true))
            return ['transaction_id'=>$transaction->id,'status'=>$transaction->status,'payment_url'=>null];
        if(!($result['success']??false)) {
            PaymentTransaction::withoutGlobalScopes()->whereKey($transaction->id)->whereNotIn('status',['completed','partially_refunded','refunded'])
                ->update(['status'=>($result['ambiguous']??false)||($result['retryable']??false)?'reconciliation_needed':'failed']);
            throw ValidationException::withMessages(['payment'=>$result['error']??'تعذر بدء الدفع.']);
        }
        $url=(string)($result['payment_url']??'');
        $host=strtolower((string)parse_url($url,PHP_URL_HOST));
        $allowed=GatewayRegistry::definition($transaction->gateway_driver)['checkout_hosts']??[];
        if(parse_url($url,PHP_URL_SCHEME)!=='https'||!in_array($host,$allowed,true)||parse_url($url,PHP_URL_USER)||parse_url($url,PHP_URL_PASS)) {
            PaymentTransaction::withoutGlobalScopes()->whereKey($transaction->id)->whereNotIn('status',['completed','partially_refunded','refunded'])->update(['status'=>'reconciliation_needed']);
            throw ValidationException::withMessages(['payment'=>'رابط بوابة الدفع غير موثق؛ تحتاج المحاولة مراجعة.']);
        }
        $expiry=$result['expires_at']??now('UTC')->addMinutes(30)->toIso8601String();
        $expiry=is_int($expiry)?\Carbon\Carbon::createFromTimestampUTC($expiry)->toIso8601String():(string)$expiry;
        DB::transaction(function() use($transaction,$result,$url,$expiry) {
            $locked=PaymentTransaction::withoutGlobalScopes()->lockForUpdate()->findOrFail($transaction->id);
            $changes=['gateway_reference'=>(string)$result['gateway_reference'],
                'payload'=>array_merge($locked->payload??[],['payment_url'=>Crypt::encryptString($url),'payment_url_expires_at'=>$expiry])];
            if(!in_array($locked->status,['completed','partially_refunded','refunded'],true)) $changes['status']='pending';
            $locked->update($changes);
        },3);
        $transaction->refresh();
        return ['transaction_id'=>$transaction->id,'status'=>$transaction->status,'payment_url'=>$result['payment_url']];
    }
}
