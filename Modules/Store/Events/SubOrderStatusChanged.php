<?php

namespace Modules\Store\Events;

// حدث تغيير حالة الطلب الفرعي من قِبَل البائع
use Modules\Store\Models\StoreSubOrder;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class SubOrderStatusChanged
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public StoreSubOrder $subOrder) {}
}
