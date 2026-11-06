<?php
namespace Modules\Payment\Services;
use Illuminate\Support\Facades\Cache;
use Modules\Payment\Models\PaymentTransaction;
class PaymentReconciliationService {
    public function run(int $limit,int $seconds): int {
        $start=microtime(true);$count=0;
        $payments=PaymentTransaction::withoutGlobalScopes()->whereNull('deleted_at')->whereNotNull('gateway_reference')
            ->where('payable_type',\Modules\Store\Models\StoreOrder::class)->whereIn('status',['initiating','pending','reconciliation_needed'])
            ->where('updated_at','<=',now('UTC')->subMinutes(2))->orderBy('updated_at')->limit($limit)->get();
        foreach($payments as $payment) {
            if(microtime(true)-$start>=$seconds)break;
            $lock=Cache::lock('payment-reconciliation:'.$payment->id,90);if(!$lock->get())continue;
            try {
                app(PaymentVerificationService::class)->verify($payment);
                $payment->refresh()->touch();$count++;
            } catch(\Throwable $error) {
                \Illuminate\Support\Facades\Log::warning('Payment reconciliation deferred',['transaction_id'=>$payment->id,'exception_class'=>get_class($error)]);
                $payment->touch();
            } finally {$lock->release();}
        }
        return $count;
    }
}
