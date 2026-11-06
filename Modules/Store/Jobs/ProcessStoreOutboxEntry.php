<?php
namespace Modules\Store\Jobs;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
class ProcessStoreOutboxEntry implements ShouldQueue {
    use Queueable;
    public int $tries=1;
    public function __construct(public int $entryId) {}
    public function handle(\Modules\Store\Services\StoreOutboxProcessor $processor): void { $processor->processId($this->entryId); }
}
