<?php

namespace App\Events;

use Modules\Accounting\Models\CashBox;
use App\Models\User;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class CashBoxAccessGranted
{
    use Dispatchable, SerializesModels;

    public $cashBox;
    public User $user;
    public ?User $actor;

    public function __construct($cashBox, User $user, ?User $actor = null)
    {
        $this->cashBox = $cashBox;
        $this->user = $user;
        $this->actor = $actor;
    }
}
