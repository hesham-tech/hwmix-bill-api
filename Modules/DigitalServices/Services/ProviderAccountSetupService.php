<?php

namespace Modules\DigitalServices\Services;

use App\Models\CashBox;
use Illuminate\Support\Facades\DB;
use Modules\DigitalServices\Models\ProviderAccount;

class ProviderAccountSetupService
{
    /**
     * Creates a new provider account (e.g., Vodafone Wallet, Fawry Machine),
     * automatically provisioning a dedicated CashBox for it to handle double-entry accounting.
     * This orchestrator unifies the UX so the manager adds the machine in one click.
     */
    public function setup(array $data, int $companyId, int $userId): ProviderAccount
    {
        return DB::transaction(function () use ($data, $companyId, $userId) {
            
            // 1. Create the dedicated CashBox (Financial Avatar) in the ERP Accounting Core
            // This ensures every wallet has a legitimate ledger for double-entry (FAC-001)
            $cashBox = CashBox::create([
                'company_id' => $companyId,
                'name' => 'خزينة: ' . $data['name'],
                'type' => $data['type'] ?? 'digital_wallet', 
                'is_active' => true,
                'created_by' => $userId,
            ]);

            // 2. Create the Provider Account (Operational Entity)
            $providerAccount = ProviderAccount::create([
                'company_id' => $companyId,
                'service_provider_id' => $data['service_provider_id'],
                'name' => $data['name'],
                'cash_box_id' => $cashBox->id, // Linked to the newly created accounting avatar
                'hwnix_cash_financial_account_id' => $data['hwnix_cash_financial_account_id'] ?? null, // Linked to Android SMS Tracker
                'is_active' => true,
                'created_by' => $userId,
            ]);

            // 3. Provision Default Commission Rules (can be expanded based on global definitions)
            // ... Logic to copy default standard rules to this company ...

            return $providerAccount;
        });
    }
}
