<?php

namespace Modules\DigitalServices\Services;

use Exception;
use Illuminate\Support\Facades\DB;
use Modules\DigitalServices\Models\ProviderAccount;
use Modules\DigitalServices\Models\ServiceDefinition;
use Modules\DigitalServices\Models\ServiceTransaction;

class ServiceProcessor
{
    public function __construct(protected FinancialPostingService $financialPosting) {}

    /**
     * Processes a new Quick POS transaction.
     */
    public function process(array $data, int $companyId, int $userId): ServiceTransaction
    {
        return DB::transaction(function () use ($data, $companyId, $userId) {
            $providerAccount = ProviderAccount::findOrFail($data['provider_account_id']);
            $definition = ServiceDefinition::findOrFail($data['service_definition_id']);
            
            // Idempotency Check: Prevent duplicate transactions from the same provider reference
            if (!empty($data['provider_reference_id'])) {
                $exists = ServiceTransaction::where('provider_account_id', $providerAccount->id)
                    ->where('provider_reference_id', $data['provider_reference_id'])
                    ->exists();
                
                if ($exists) {
                    throw new Exception("هذه العملية مسجلة مسبقاً بهذا الرقم المرجعي. (Idempotency Error)");
                }
            }

            // Create Transaction Record (Immutable snapshot of the operation and commissions)
            $transaction = ServiceTransaction::create([
                'company_id' => $companyId,
                'service_definition_id' => $definition->id,
                'provider_account_id' => $providerAccount->id,
                'cash_box_id' => $data['cash_box_id'], // Shop's physical drawer affected
                'branch_id' => $data['branch_id'] ?? null,
                'operation_type' => $definition->operation_type,
                
                // Financial Fields
                'service_amount' => $data['service_amount'],
                'network_fee' => $data['network_fee'] ?? 0,
                'shop_commission' => $data['shop_commission'] ?? 0,
                'provider_amount' => $data['provider_amount'],
                'cash_amount' => $data['cash_amount'],
                
                // Status handling
                'status' => $data['status'] ?? 'Completed', // Can be 'Awaiting External Confirmation'
                'provider_reference_id' => $data['provider_reference_id'] ?? null,
                'executed_at' => now(),
                'created_by' => $userId,
            ]);

            // If completed immediately, post financials to the Engine
            if ($transaction->status === 'Completed') {
                $this->financialPosting->postTransaction($transaction);
            }

            return $transaction;
        });
    }
}
