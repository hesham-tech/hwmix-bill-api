<?php

namespace Modules\Store\Events;

// حدث إنشاء طلب جديد في المتجر الإلكتروني
use Modules\Store\Models\StoreOrder;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class OrderPlaced
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public StoreOrder $order) {}
}
