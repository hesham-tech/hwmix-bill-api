<?php
namespace Modules\Store\Jobs;
use Illuminate\Contracts\Queue\{ShouldQueue,ShouldBeUnique};
use Illuminate\Foundation\Queue\Queueable;
use Modules\Store\Models\StoreSetting;
class ProbeStoreQueue implements ShouldQueue,ShouldBeUnique {
    use Queueable;
    public int $uniqueFor=3600;public int $tries=1;public int $timeout=10;
    public function __construct(public string $targetConnection,public int $issuedAt){$this->onConnection($targetConnection);}
    public function uniqueId(): string {return 'store-queue-probe:'.$this->targetConnection;}
    public function handle(): void {
        StoreSetting::updateOrCreate(['key'=>'queue_worker_heartbeat:'.$this->targetConnection],['value'=>[
            'completed_at'=>now('UTC')->toIso8601String(),'connection'=>$this->targetConnection,'lag_seconds'=>max(0,time()-$this->issuedAt),
        ]]);
    }
}
