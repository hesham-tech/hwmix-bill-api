<?php

namespace Modules\Accounting\Observers;

use Modules\Accounting\Models\Transaction;
use App\Events\TransactionCreated;

/**
 * مراقب المعاملات المالية (TransactionObserver) - موديول المحاسبة
 */
class TransactionObserver
{
    public function created(Transaction $transaction): void
    {
        event(new TransactionCreated($transaction));
    }
}
