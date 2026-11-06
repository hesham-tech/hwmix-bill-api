<?php
namespace Modules\Store\Services;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Payment\Models\PaymentTransaction;
use Modules\Store\Models\StoreRefund;
class StorePaymentProofService {
    public function claim(string $scope,string $reference,Model $owner,int $amount,string $currency): void {
        if(!DB::transactionLevel()||!$owner->getKey()||$amount<=0)throw new \LogicException('Payment proof requires an atomic financial transaction.');
        $normalized=str_starts_with($scope,'gateway:')?trim($reference):mb_strtoupper(preg_replace('/\s+/u','',trim($reference)));
        if($normalized==='')throw ValidationException::withMessages(['reference'=>'مرجع التحويل الفردي مطلوب.']);
        $hash=hash('sha256',$normalized);
        $proof=DB::table('store_payment_proofs')->where('scope',$scope)->where('reference_hash',$hash)->lockForUpdate()->first();
        if($proof){
            if($proof->owner_type!==$owner->getMorphClass()||(int)$proof->owner_id!==(int)$owner->getKey()||(int)$proof->amount_minor!==$amount||$proof->currency!==$currency)
                throw ValidationException::withMessages(['reference'=>'سبق استخدام هذا المرجع لتحويل آخر. أدخل مرجع العملية الفردية واسم البنك؛ لا تستخدم مرجع دفعة مجمعة.']);
            return;
        }
        DB::table('store_payment_proofs')->insert(['scope'=>$scope,'reference_hash'=>$hash,'owner_type'=>$owner->getMorphClass(),'owner_id'=>$owner->getKey(),'amount_minor'=>$amount,'currency'=>$currency,'created_at'=>now('UTC')]);
    }
    public function refundScope(StoreRefund $refund,string $payer): string {
        if($payer==='gateway'){
            $transaction=PaymentTransaction::withoutGlobalScopes()->findOrFail($refund->payment_transaction_id);
            return 'gateway:'.$transaction->gateway_driver.':'.($transaction->payment_gateway_id??0).':'.(int)($transaction->gateway_config_snapshot['_test_mode']??false);
        }
        return $payer==='seller'?'manual:seller:'.\Modules\Store\Models\StoreSubOrder::findOrFail($refund->store_sub_order_id)->company_id:'manual:platform';
    }
}
