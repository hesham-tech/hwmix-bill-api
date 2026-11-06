<?php
namespace Modules\Store\Console;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\{Cache,DB};
use Modules\Store\Models\{StoreOrder,StoreSubOrder};
use Modules\Store\Services\{StoreOutboxProcessor,StoreOrderService,SellerFinanceService};

class StoreTickCommand extends Command {
    protected $signature='store:tick {--limit=10} {--seconds=20}';
    protected $description='Process bounded durable store tasks, expiring holds and eligible settlements.';
    public function handle(StoreOutboxProcessor $processor): int {
        $limit=max(1,min(50,(int)$this->option('limit'))); $seconds=max(1,min(40,(int)$this->option('seconds')));
        $started=microtime(true); $lock=Cache::lock('store:tick',90);
        if(!$lock->get()) { $this->line('Store tick is already running.'); return self::SUCCESS; }
        try {
            if(config('queue.default')!=='sync'&&Cache::add('store:queue-probe:'.config('queue.default'),true,now('UTC')->addMinute()))
                \Modules\Store\Jobs\ProbeStoreQueue::dispatch((string)config('queue.default'),time());
            $expired=0; $settled=0;$scan=app(\Modules\Store\Services\StoreTaskScan::class);
            foreach($scan->next(StoreOrder::where('status','pending')->whereNotNull('hold_expires_at')->where('hold_expires_at','<=',now('UTC')),'holds',$limit) as $order) {
                if(microtime(true)-$started>=$seconds) break;
                $scan->advance('holds',$order->id);
                try {app(StoreOrderService::class)->cancelOrder($order,'hold_expired'); $expired++;}
                catch(\Throwable $error){$scan->review('hold',$order->id,$error);}
            }
            foreach($scan->next(StoreSubOrder::where('status','delivered')->whereNull('settled_at')->whereNotNull('settlement_available_at')
                ->where('settlement_available_at','<=',now('UTC')),'settlements',$limit) as $sub) {
                if(microtime(true)-$started>=$seconds) break;
                $scan->advance('settlements',$sub->id);
                try { DB::transaction(function() use($sub) {
                    StoreOrder::lockForUpdate()->findOrFail($sub->store_order_id);
                    $locked=StoreSubOrder::lockForUpdate()->findOrFail($sub->id);
                    app(SellerFinanceService::class)->settle($locked);
                },3); }catch(\Throwable $error){$scan->review('settlement',$sub->id,$error);}
                $settled++;
            }
            $remaining=max(0,$seconds-(int)ceil(microtime(true)-$started));
            if($remaining>0) app(\Modules\Payment\Services\PaymentReconciliationService::class)->run($limit,$remaining);
            $remaining=max(0,$seconds-(int)ceil(microtime(true)-$started));
            if ($remaining>0) app(\Modules\Store\Services\StorePayoutService::class)->createScheduledRequests($limit,$remaining);
            $remaining=max(0,$seconds-(int)ceil(microtime(true)-$started));
            $processed=$remaining>0?$processor->drain($limit,$remaining):0;
            \Modules\Store\Models\StoreSetting::updateOrCreate(['key'=>'operations_heartbeat'],['value'=>[
                'completed_at'=>now('UTC')->toIso8601String(),'queue_connection'=>config('queue.default'),
                'expired'=>$expired,'settlement_checks'=>$settled,'outbox'=>$processed,
            ]]);
            $this->info("Store tick: holds={$expired}, settlement_checks={$settled}, outbox={$processed}");
            return self::SUCCESS;
        } finally { $lock->release(); }
    }
}
