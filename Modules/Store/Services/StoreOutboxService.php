<?php
namespace Modules\Store\Services;
use Modules\Store\Models\StoreOutbox;
class StoreOutboxService
{
    public function enqueue(string $kind,string $key,array $payload,$at=null): StoreOutbox {
        return StoreOutbox::firstOrCreate(['deduplication_key'=>$key],[
            'kind'=>$kind,'payload'=>$payload,'available_at'=>$at??now(),'status'=>'pending',
        ]);
    }
}
