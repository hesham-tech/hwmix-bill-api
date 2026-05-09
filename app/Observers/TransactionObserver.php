<?php

namespace App\Observers;

use App\Models\Transaction;
use App\Events\TransactionCreated;

class TransactionObserver
{
    /**
     * Handle the Transaction "created" event.
     */
    public function created(Transaction $transaction): void
    {
        event(new TransactionCreated($transaction));
    }
}
