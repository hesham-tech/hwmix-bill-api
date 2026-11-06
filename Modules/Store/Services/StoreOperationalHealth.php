<?php
namespace Modules\Store\Services;
use Modules\Store\Models\StoreSetting;
class StoreOperationalHealth {
    public function issues(): array {
        $issues=[];$heartbeat=StoreSetting::where('key','operations_heartbeat')->value('value');
        if(empty($heartbeat['completed_at'])||\Carbon\Carbon::parse($heartbeat['completed_at'])->lt(now('UTC')->subMinutes(5)))$issues[]='تشغيل Cron والتحقق من نبض جدولة المتجر خلال آخر خمس دقائق.';
        if(config('queue.default')!=='sync'){
            $worker=StoreSetting::where('key','queue_worker_heartbeat:'.config('queue.default'))->value('value');
            if(empty($worker['completed_at'])||\Carbon\Carbon::parse($worker['completed_at'])->lt(now('UTC')->subMinutes(5))||(int)($worker['lag_seconds']??PHP_INT_MAX)>120)
                $issues[]='تشغيل عامل الطابور والتحقق من نبضه وعدم تراكم المهام.';
        }
        return $issues;
    }
    public function assertAcceptingOrders(): void {
        if(app()->environment('production')&&$this->issues())abort(503,'إتمام الطلبات متوقف مؤقتًا. حاول مجددًا بعد قليل.');
    }
}
