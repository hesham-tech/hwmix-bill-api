<?php
namespace Modules\Store\Services;
use App\Support\Money;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Store\Models\{StorePayout,StoreSellerAccount,StoreSellerProfile,StoreAccountHold};

class StorePayoutService
{
    public function __construct(private StoreLedgerService $ledger,private SellerPolicyService $policies) {}
    public function available(StoreSellerAccount $account): int {
        $held=(string)StoreAccountHold::where('account_id',$account->id)->where('status','active')->lockForUpdate()->get(['balance_minor'])->sum('balance_minor');
        return max(0,Money::minor(bcsub(bcsub((string)$account->balance_minor,(string)$account->payout_held_minor,0),$held,0),0));
    }
    public function request(int $companyId,int $amount,string $key): StorePayout {
        return DB::transaction(function() use($companyId,$amount,$key) {
            $account=$this->ledger->account($companyId,app(StoreSettingsService::class)->get()['currency']);
            $account=StoreSellerAccount::lockForUpdate()->findOrFail($account->id);
            $existing=StorePayout::where('operation_key',$key)->first();
            if($existing) {
                if($existing->account_id!==$account->id||$existing->amount_minor!==$amount) throw new \LogicException('Payout operation key was reused.');
                return $existing;
            }
            $policy=$this->policies->resolve($companyId);
            if($amount<=0||$amount<(int)$policy['minimum_payout_minor']||$amount>$this->available($account))
                throw ValidationException::withMessages(['amount'=>'المبلغ يتجاوز الرصيد المتاح للصرف أو لا يبلغ الحد المطلوب. الرصيد المسبق وحد الائتمان غير قابلين للسحب.']);
            $profile=StoreSellerProfile::where('company_id',$companyId)->first();
            if(!$profile?->payout_destination) throw ValidationException::withMessages(['destination'=>'جهة صرف معتمدة للبائع مطلوبة.']);
            $payout=StorePayout::create(['account_id'=>$account->id,'operation_key'=>$key,'amount_minor'=>$amount,
                'destination_snapshot'=>$profile->payout_destination,'method'=>'manual','requested_by'=>auth()->id()]);
            $account->update(['payout_held_minor'=>$account->payout_held_minor+$amount]);
            return $payout;
        },3);
    }
    public function approve(StorePayout $payout): void {
        DB::transaction(function() use($payout) {
            $account=StoreSellerAccount::lockForUpdate()->findOrFail($payout->account_id);
            $payout=StorePayout::lockForUpdate()->findOrFail($payout->id);
            if($payout->status==='approved'||$payout->status==='paid') return;
            if($payout->status!=='requested') throw ValidationException::withMessages(['status'=>'طلب الصرف غير قابل للاعتماد.']);
            $commissionHolds=(string)StoreAccountHold::where('account_id',$account->id)->where('status','active')->lockForUpdate()->get(['balance_minor'])->sum('balance_minor');
            if(bccomp((string)$account->balance_minor,bcadd((string)$account->payout_held_minor,$commissionHolds,0),0)<0)
                throw ValidationException::withMessages(['balance'=>'تغير الرصيد بسبب استرداد أو تسوية. راجع طلب الصرف.']);
            $payout->update(['status'=>'approved','approved_by'=>auth()->id()]);
        },3);
    }
    public function paid(StorePayout $payout,string $reference): void {
        DB::transaction(function() use($payout,$reference) {
            $account=StoreSellerAccount::lockForUpdate()->findOrFail($payout->account_id);
            $payout=StorePayout::lockForUpdate()->findOrFail($payout->id);
            if($payout->status==='paid') {
                if($payout->provider_reference!==$reference) throw new \LogicException('Payout reference does not match the recorded transfer.');
                return;
            }
            app(StorePaymentProofService::class)->claim('manual:platform',$reference,$payout,$payout->amount_minor,$account->currency);
            if($payout->status!=='approved'||$account->payout_held_minor<$payout->amount_minor)
                throw ValidationException::withMessages(['status'=>'اعتمد الصرف قبل إثبات التحويل الفعلي.']);
            $this->ledger->post($account,'balance_minor',-$payout->amount_minor,'payout:'.$payout->id.':paid','seller_payout','asset:bank',
                ['reference'=>$reference,'reference_type'=>'store_payout','reference_id'=>$payout->id]);
            $account->refresh()->update(['payout_held_minor'=>$account->payout_held_minor-$payout->amount_minor]);
            $operation=app(\App\Contracts\FinancialEngineInterface::class)->postMarketplaceJournal($payout,$account->company_id,'marketplace_payout',[
                ['type'=>'debit','account_type'=>'asset','sub_account_type'=>'bank_reconciliation_clearing','amount_minor'=>$payout->amount_minor],
                ['type'=>'credit','account_type'=>'asset','sub_account_type'=>'marketplace_clearing','amount_minor'=>$payout->amount_minor],
            ],['amount'=>Money::decimal($payout->amount_minor),'reference'=>$reference,'currency'=>$account->currency]);
            $payout->update(['status'=>'paid','paid_at'=>now('UTC'),'provider_reference'=>$reference,'erp_operation_id'=>$operation]);
        },3);
    }
    public function reject(StorePayout $payout,string $reason): void {
        DB::transaction(function() use($payout,$reason) {
            $account=StoreSellerAccount::lockForUpdate()->findOrFail($payout->account_id);
            $payout=StorePayout::lockForUpdate()->findOrFail($payout->id);
            if($payout->status==='rejected') return;
            if(!in_array($payout->status,['requested','approved'],true)||$account->payout_held_minor<$payout->amount_minor)
                throw ValidationException::withMessages(['status'=>'لا يمكن رفض طلب صرف منفذ أو غير صالح.']);
            $account->update(['payout_held_minor'=>$account->payout_held_minor-$payout->amount_minor]);
            $payout->update(['status'=>'rejected','notes'=>$reason]);
        },3);
    }
    public function createScheduledRequests(int $limit=10,int $seconds=10): int {
        $now=now(config('store.business_timezone','Africa/Cairo')); $count=0;$started=microtime(true);$scan=app(StoreTaskScan::class);
        foreach($scan->next(StoreSellerAccount::where('balance_minor','>',0),'payouts',$limit) as $account) {
            if(microtime(true)-$started>=$seconds)break;
            $scan->advance('payouts',$account->id);
            try {
            $policy=$this->policies->resolve($account->company_id);
            if(!$policy['automatic_payout'])continue;
            $due=$now->copy()->startOfWeek(\Carbon\Carbon::SUNDAY)->addDays((int)$policy['payout_weekday'])->setTime((int)$policy['payout_hour'],0);
            if($due->isFuture())$due->subWeek();
            if($account->created_at->gt($due))continue;
            $key='auto:'.$account->id.':'.$due->toDateString();
            if(StorePayout::where('operation_key',$key)->exists()) continue;
            $amount=$this->available($account);
            if($amount>0&&$amount>=(int)$policy['minimum_payout_minor']) {
                try { $this->request($account->company_id,$amount,$key); $count++; }
                catch(ValidationException $error) { $scan->review('payout',$account->id,$error); }
            }
            } catch(\Throwable $error) {$scan->review('payout',$account->id,$error);}
        }
        return $count;
    }
}
