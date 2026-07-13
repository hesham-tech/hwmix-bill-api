<?php

namespace App\Events;

use Modules\Accounting\Models\CashBox;
use App\Models\User;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class CashBoxCreated
{
    use Dispatchable, SerializesModels;

    public $cashBox;
    public ?User $actor;

    public function __construct($cashBox, ?User $actor = null)
    {
        $this->cashBox = $cashBox;
        $this->actor = $actor;
    }
}
