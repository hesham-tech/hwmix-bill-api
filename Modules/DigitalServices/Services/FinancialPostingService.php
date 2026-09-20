<?php

namespace Modules\DigitalServices\Services;

// use App\Contracts\FinancialEngineInterface;
use Modules\DigitalServices\Models\ServiceTransaction;

class FinancialPostingService
{
    // public function __construct(protected FinancialEngineInterface $financialEngine) {}

    /**
     * Posts a completed Service Transaction to the Financial Engine.
     */
    public function postTransaction(ServiceTransaction $transaction): void
    {
        if ($transaction->status !== 'Completed') {
            return; // Only post completed transactions
        }

        // TODO: Map the transaction to the HWNix FinancialEngineInterface payload.
        // Rule FAC-001: All financial impacts must go through the FinancialEngine via double-entry.
        // Example:
        // Debit: $transaction->cash_box_id (Physical Drawer)
        // Credit: $transaction->providerAccount->cash_box_id (Virtual Machine Ledger)
        // Credit: Revenue Account ($transaction->shop_commission)
        
        /*
        $this->financialEngine->recordDoubleEntry([
            'company_id' => $transaction->company_id,
            'description' => "عملية خدمات رقمية - " . $transaction->provider_reference_id,
            'entries' => [
                // 1. Drawer Entry
                [
                    'account_type' => 'cash_box',
                    'account_id' => $transaction->cash_box_id,
                    'amount' => $transaction->cash_amount, // Positive or negative based on operation_type
                ],
                // 2. Provider Ledger Entry (The Avatar CashBox)
                [
                    'account_type' => 'cash_box',
                    'account_id' => $transaction->providerAccount->cash_box_id,
                    'amount' => -$transaction->provider_amount, 
                ],
                // 3. Commission/Revenue
                [
                    'account_type' => 'revenue',
                    'amount' => $transaction->shop_commission,
                ]
            ]
        ]);
        */

        $transaction->update(['settled_at' => now()]);
    }
}
