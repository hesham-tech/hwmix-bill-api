<?php

namespace Modules\DigitalServices\Services;

use Modules\DigitalServices\Models\ProviderAccount;
use Modules\DigitalServices\Models\ReconciliationVariance;

class ReconciliationService
{
    /**
     * توليد فروقات المطابقة (Variances) بين الرصيد الدفتري (ERP) والرصيد الفعلي (SMS/آلي).
     * هذه الفروقات تحفظ في جدول للتحقيق فيها بدلاً من تسجيلها כإيراد تلقائي.
     */
    public function generateVariance(ProviderAccount $account, int $companyId, int $userId): ReconciliationVariance
    {
        // 1. Expected Balance (Book Balance from internal CashBox logic)
        // HWNix CashBoxes usually have a method to calculate or return current balance
        $expectedBalance = 0; 
        if ($account->cash_box_id) {
            // Placeholder: Call standard balance method on CashBox model
            // $expectedBalance = $account->cashBox->balance(); 
        }
        
        // 2. Actual External Balance (From SMS Tracker)
        $actualBalance = 0;
        if ($account->hwnix_cash_financial_account_id) {
            // Placeholder: Read the actual_balance updated by the Android App SMS Listener
            // $actualBalance = $account->hwnixCashAccount->actual_balance;
        } else {
            // For Fawry machines without SMS, this would either be manually input during shift close
            // or fetched via an external API if applicable.
        }

        // 3. Calculate Variance
        $varianceAmount = $actualBalance - $expectedBalance;
        $status = ($varianceAmount == 0) ? 'Matched' : 'Pending Investigation';

        // 4. Document Variance
        return ReconciliationVariance::create([
            'company_id' => $companyId,
            'provider_account_id' => $account->id,
            'expected_balance' => $expectedBalance,
            'actual_balance' => $actualBalance,
            'variance_amount' => $varianceAmount,
            'status' => $status,
            'created_by' => $userId,
        ]);
    }
}
