<?php
namespace App\Jobs;
use App\Models\{Company,DailySalesSummary,MonthlySalesSummary,FinancialLedger,Invoice};
use App\Support\Money;
use Carbon\CarbonImmutable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;

class UpdateDailySalesSummary implements ShouldQueue
{
    use Dispatchable,InteractsWithQueue,Queueable,SerializesModels;
    public function __construct(protected string $date,protected int $companyId) {}
    public function handle(): void {
        DB::transaction(function() {
            $company=Company::withoutGlobalScopes()->lockForUpdate()->findOrFail($this->companyId);
            $timezone=$company->settings['timezone']??config('store.business_timezone','Africa/Cairo');
            if(!in_array($timezone,timezone_identifiers_list(),true)) $timezone='Africa/Cairo';
            $day=CarbonImmutable::parse($this->date,$timezone)->startOfDay();
            $start=$day->utc(); $end=$day->addDay()->utc();
            $revenue=$cogs=$expenses='0';
            foreach(FinancialLedger::withoutGlobalScopes()->where('company_id',$this->companyId)
                ->where('entry_date','>=',$start)->where('entry_date','<',$end)->cursor() as $ledger) {
                $amount=(string)Money::minor($ledger->amount);
                if($ledger->account_type==='revenue') {
                    $revenue=$ledger->type==='credit'?bcadd($revenue,$amount,0):bcsub($revenue,$amount,0);
                } elseif($ledger->account_type==='expense') {
                    $isCogs=$ledger->sub_account_type?$ledger->sub_account_type==='cost_of_goods_sold':str_contains($ledger->source_type,'Invoice');
                    $delta=$ledger->type==='debit'?$amount:'-'.$amount;
                    if($isCogs) $cogs=bcadd($cogs,$delta,0); else $expenses=bcadd($expenses,$delta,0);
                }
            }
            $count=Invoice::withoutGlobalScopes()->whereNull('deleted_at')->where('company_id',$this->companyId)
                ->where(function($q) use($start,$end) {
                    $q->whereDate('issue_date',$this->date)->orWhere(fn($q)=>$q->whereNull('issue_date')->where('created_at','>=',$start)->where('created_at','<',$end));
                })->whereIn('status',['confirmed','paid','partially_paid'])->whereHas('invoiceType',fn($q)=>$q->withoutGlobalScopes()
                    ->whereIn('code',['sale','installment_sale','sales_invoice','sales']))->count();
            $gross=bcsub($revenue,$cogs,0); $net=bcsub($gross,$expenses,0);
            DailySalesSummary::withoutGlobalScopes()->updateOrCreate(['date'=>$day->toDateString(),'company_id'=>$this->companyId],[
                'total_revenue'=>bcdiv($revenue,'100',2),'total_cogs'=>bcdiv($cogs,'100',2),'total_expenses'=>bcdiv($expenses,'100',2),
                'gross_profit'=>bcdiv($gross,'100',2),'net_profit'=>bcdiv($net,'100',2),'sales_count'=>$count,
            ]);
            $month=$day->format('Y-m');
            $stats=DailySalesSummary::withoutGlobalScopes()->where('company_id',$this->companyId)->where('date','like',$month.'-%')
                ->selectRaw('SUM(total_revenue) AS revenue,SUM(total_cogs) AS cogs,SUM(total_expenses) AS expenses,SUM(net_profit) AS net_profit,SUM(sales_count) AS count')->first();
            $decimal=fn($value)=>is_float($value)?number_format($value,2,'.',''):(string)($value??'0.00');
            MonthlySalesSummary::withoutGlobalScopes()->updateOrCreate(['year_month'=>$month,'company_id'=>$this->companyId],[
                'total_revenue'=>$decimal($stats->revenue),'total_cogs'=>$decimal($stats->cogs),'total_expenses'=>$decimal($stats->expenses),
                'net_profit'=>$decimal($stats->net_profit),'sales_count'=>(int)$stats->count,
            ]);
        },3);
    }
}
