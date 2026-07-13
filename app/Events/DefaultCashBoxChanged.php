<?php

namespace App\Events;

use Modules\Accounting\Models\CashBox;
use App\Models\User;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class DefaultCashBoxChanged
{
    use Dispatchable, SerializesModels;

    public $cashBox;
    public User $user;
    public ?int $oldDefaultId;
    public ?int $newDefaultId;
    public ?User $actor;

    public function __construct($cashBox, User $user, ?int $oldDefaultId, ?int $newDefaultId, ?User $actor = null)
    {
        $this->cashBox = $cashBox;
        $this->user = $user;
        $this->oldDefaultId = $oldDefaultId;
        $this->newDefaultId = $newDefaultId;
        $this->actor = $actor;
    }
}
