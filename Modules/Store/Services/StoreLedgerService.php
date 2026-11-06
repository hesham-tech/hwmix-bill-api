<?php
namespace Modules\Store\Services;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Store\Models\StoreSellerAccount;

class StoreLedgerService
{
    public function account(int $companyId,string $currency='EGP'): StoreSellerAccount {
        return StoreSellerAccount::firstOrCreate(['company_id'=>$companyId,'currency'=>$currency]);
    }

    /** Balanced immutable journal, using positive debit / negative credit lines. */
    public function journal(string $key,string $kind,string $currency,array $lines,array $metadata=[]): void {
        $sum='0';
        foreach($lines as $line) $sum=bcadd($sum,(string)$line['amount_minor'],0);
        if(bccomp($sum,'0',0)!==0) throw new \LogicException('Unbalanced marketplace journal.');
        $fingerprint=hash('sha256',json_encode([$kind,$currency,array_map(fn($line)=>[
            $line['account_code'],$line['company_id']??null,(string)$line['amount_minor'],
        ],$lines)],JSON_THROW_ON_ERROR));
        $existing=DB::table('store_financial_journals')->where('operation_key',$key)->first();
        if($existing) {
            if(!hash_equals($existing->fingerprint,$fingerprint)) throw new \LogicException('Journal operation key was reused.');
            return;
        }
        $id=DB::table('store_financial_journals')->insertGetId([
            'operation_key'=>$key,'kind'=>$kind,'currency'=>$currency,'fingerprint'=>$fingerprint,
            'metadata'=>json_encode($metadata,JSON_THROW_ON_ERROR),'created_at'=>now(),
        ]);
        foreach($lines as $line) DB::table('store_financial_lines')->insert([
            'journal_id'=>$id,'account_code'=>$line['account_code'],'company_id'=>$line['company_id']??null,
            'amount_minor'=>$line['amount_minor'],
        ]);
    }

    public function post(StoreSellerAccount $account,string $bucket,int $amount,string $key,string $kind,string $counter,array $metadata=[]): StoreSellerAccount {
        if(!in_array($bucket,['balance_minor','prepaid_minor','pending_minor'],true))
            throw new \LogicException('Invalid seller account bucket.');
        return DB::transaction(function() use($account,$bucket,$amount,$key,$kind,$counter,$metadata) {
            $locked=StoreSellerAccount::lockForUpdate()->findOrFail($account->id);
            $existing=DB::table('store_seller_ledger')->where('operation_key',$key)->first();
            if($existing) {
                if((int)$existing->account_id!==$locked->id||(int)$existing->amount_minor!==$amount||$existing->bucket!==$bucket)
                    throw new \LogicException('Financial operation key was reused with different data.');
                return $locked;
            }
            $next=bcadd((string)$locked->$bucket,(string)$amount,0);
            if(bccomp($next,(string)PHP_INT_MAX,0)>0||bccomp($next,(string)(-PHP_INT_MAX),0)<0)
                throw new \OverflowException('Seller balance overflow.');
            if($bucket!=='balance_minor'&&bccomp($next,'0',0)<0)
                throw ValidationException::withMessages(['balance'=>'الرصيد المحجوز أو المسبق غير كافٍ.']);
            $locked->update([$bucket=>(int)$next]);
            DB::table('store_seller_ledger')->insert([
                'account_id'=>$locked->id,'operation_key'=>$key,'kind'=>$kind,'bucket'=>$bucket,
                'amount_minor'=>$amount,'balance_after_minor'=>$next,'created_by'=>auth()->id(),
                'reference_type'=>$metadata['reference_type']??null,'reference_id'=>$metadata['reference_id']??null,
                'metadata'=>json_encode($metadata,JSON_THROW_ON_ERROR),'created_at'=>now(),
            ]);
            $this->journal($key,$kind,$locked->currency,[
                ['account_code'=>'liability:seller:'.$bucket,'company_id'=>$locked->company_id,'amount_minor'=>-$amount],
                ['account_code'=>$counter,'amount_minor'=>$amount],
            ],$metadata);
            return $locked;
        },3);
    }

    public function topUp(int $companyId,int $amount,string $key,string $reference,string $currency='EGP',bool $debtOnly=false): StoreSellerAccount {
        if($amount<=0||trim($reference)==='') throw ValidationException::withMessages(['amount'=>'قيمة وإثبات التحصيل مطلوبان.']);
        return DB::transaction(function() use($companyId,$amount,$key,$reference,$currency,$debtOnly) {
            $account=$this->account($companyId,$currency);
            $account=StoreSellerAccount::lockForUpdate()->findOrFail($account->id);
            // A top-up first pays actual outstanding debt; it never creates a withdrawable credit line.
            $offset=$debtOnly||(app(SellerPolicyService::class)->resolve($companyId)['automatic_debt_offset']??true);
            $debt=$offset?min($amount,max(0,-$account->balance_minor)):0;
            $root='topup:'.$key;
            $receipt=DB::table('store_financial_journals')->where('operation_key',$root.':receipt')->first();
            if($receipt) {
                $original=json_decode($receipt->metadata,true);
                if(($original['reference']??null)!==$reference||(int)($original['company_id']??0)!==$companyId||(int)($original['amount_minor']??0)!==$amount||(bool)($original['debt_only']??false)!==$debtOnly)
                    throw new \LogicException('Top-up operation key was reused.');
                return $account;
            }
            $referenceHash=hash('sha256',mb_strtoupper(preg_replace('/\s+/u','',trim($reference))));
            $request=DB::table('store_funding_requests')->where('operation_key',$key)->lockForUpdate()->first();
            if($request) {
                $saved=DB::table('store_funding_receipts')->where('id',$request->receipt_id)->first();
                if((int)$saved->account_id!==$account->id||$saved->reference_hash!==$referenceHash||(int)$saved->amount_minor!==$amount||(bool)$saved->debt_only!==$debtOnly)
                    throw new \LogicException('Funding request key was reused.');
                return $account;
            }
            $proof=DB::table('store_funding_receipts')->where('account_id',$account->id)->where('reference_hash',$referenceHash)->lockForUpdate()->first();
            if($proof) {
                if((int)$proof->amount_minor!==$amount||(bool)$proof->debt_only!==$debtOnly)
                    throw ValidationException::withMessages(['reference'=>'سبق قيد هذا التحويل بمبلغ أو غرض مختلف. راجع كشف الحساب.']);
                DB::table('store_funding_requests')->insert(['operation_key'=>$key,'receipt_id'=>$proof->id,'created_at'=>now('UTC')]);
                return $account;
            }
            if($debtOnly&&($debt<=0||$amount>$debt)) throw ValidationException::withMessages(['amount'=>'مبلغ تسوية المديونية يجب ألا يتجاوز المديونية الفعلية.']);
            $proofId=DB::table('store_funding_receipts')->insertGetId(['account_id'=>$account->id,'reference_hash'=>$referenceHash,'amount_minor'=>$amount,'debt_only'=>$debtOnly,'credited_at'=>now('UTC')]);
            DB::table('store_funding_requests')->insert(['operation_key'=>$key,'receipt_id'=>$proofId,'created_at'=>now('UTC')]);
            $this->journal($root.':receipt','topup_received',$currency,[
                ['account_code'=>'asset:bank','amount_minor'=>$amount],
                ['account_code'=>'clearing:topups','amount_minor'=>-$amount],
            ],['reference'=>$reference,'company_id'=>$companyId,'amount_minor'=>$amount,'debt_only'=>$debtOnly]);
            if($debt) $this->post($account,'balance_minor',$debt,$root.':debt','debt_payment','clearing:topups',['reference'=>$reference]);
            if($amount>$debt) $this->post($account,'prepaid_minor',$amount-$debt,$root.':prepaid','prepaid_topup','clearing:topups',['reference'=>$reference]);
            $source=\Modules\Store\Models\StoreFinancialJournal::where('operation_key',$root.':receipt')->firstOrFail();
            app(StorePaymentProofService::class)->claim('manual:incoming:platform',$reference,$source,$amount,$currency);
            $lines=[['type'=>'credit','account_type'=>'asset','sub_account_type'=>'bank_reconciliation_clearing','amount_minor'=>$amount]];
            if($debt) $lines[]=['type'=>'debit','account_type'=>'asset','sub_account_type'=>'marketplace_clearing','amount_minor'=>$debt];
            if($amount>$debt) $lines[]=['type'=>'debit','account_type'=>'asset','sub_account_type'=>'marketplace_prepaid','amount_minor'=>$amount-$debt];
            app(\App\Contracts\FinancialEngineInterface::class)->postMarketplaceJournal($source,$companyId,'marketplace_funding',$lines,[
                'amount'=>\App\Support\Money::decimal($amount),'currency'=>$currency,'reference'=>$reference,
            ]);
            return $account->refresh();
        },3);
    }
}
