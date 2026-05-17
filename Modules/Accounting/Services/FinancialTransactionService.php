<?php

namespace Modules\Accounting\Services;

use App\Models\Transaction;
use Modules\Core\Services\DocumentServiceInterface;
use Modules\Sales\Models\Invoice;

class FinancialTransactionService implements DocumentServiceInterface
{
    public function create(array $data): Invoice|array
    {
        $transaction = Transaction::create([
            'user_id' => $data['user_id'] ?? null,
            'cashbox_id' => $data['cashbox_id'] ?? null,
            'target_user_id' => $data['target_user_id'] ?? null,
            'target_cashbox_id' => $data['target_cashbox_id'] ?? null,
            'created_by' => $data['created_by'] ?? null,
            'company_id' => $data['company_id'] ?? null,
            'type' => $data['invoice_type_code'] ?? $data['type'] ?? 'receipt',
            'amount' => $data['total_amount'] ?? $data['amount'] ?? 0,
            'description' => $data['description'] ?? null,
        ]);

        return [
            'transaction_id' => $transaction->id,
            'amount' => $transaction->amount,
        ];
    }

    public function update(array $data, Invoice $invoice): Invoice
    {
        return $invoice;
    }

    public function cancel(Invoice $invoice): Invoice
    {
        return $invoice;
    }
}
