<?php
require __DIR__ . '/../../../vendor/autoload.php';
$app = require_once __DIR__ . '/../../../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;

$stakeholders = DB::table('stakeholder_financial_balances')->get();

$checkedCount = 0;
$matchedCount = 0;
$diffCount = 0;
$totalExpected = 0;
$totalActual = 0;
$totalDifferences = 0;

$report = "# Phase 5: Balance Reconciliation Report\n\n";
$report .= "## Summary\n";
$report .= "- Total Stakeholders Checked: {checkedCount}\n";
$report .= "- Matched: {matchedCount}\n";
$report .= "- Mismatched: {diffCount}\n";
$report .= "- Total Actual Balance (StakeholderFinancialBalance): {totalActual}\n";
$report .= "- Total Expected Balance (Calculated): {totalExpected}\n";
$report .= "- Total Absolute Difference: {totalDifferences}\n\n";

$report .= "## Mismatches Details\n\n";
$report .= "| User ID | Company ID | Relation Type | Actual Balance | Expected Balance | Ledger Balance | Difference |\n";
$report .= "|---------|------------|---------------|----------------|------------------|----------------|------------|\n";

foreach ($stakeholders as $sh) {
    $userId = $sh->user_id;
    $companyId = $sh->company_id;
    $relType = $sh->relation_type;
    $actualBalance = (float) $sh->balance;
    
    $checkedCount++;
    $totalActual += $actualBalance;

    // Opening Balance
    $openingBalance = (float) DB::table('financial_operations')
        ->where('type', 'opening_balance')
        ->where('source_type', 'App\Models\User')
        ->where('source_id', $userId)
        ->where('company_id', $companyId)
        ->sum('amount');
        
    // Get user invoices to link ledger entries
    $userInvoices = DB::table('invoices')
        ->where('user_id', $userId)
        ->where('company_id', $companyId)
        ->pluck('id')->toArray();
        
    $userPayments = DB::table('payments')
        ->where('user_id', $userId)
        ->where('company_id', $companyId)
        ->pluck('id')->toArray();
        
    // Invoices
    $salesSum = (float) DB::table('invoices')
        ->where('user_id', $userId)
        ->where('company_id', $companyId)
        ->whereIn('invoice_type_code', ['sale', 'installment_sale'])
        ->whereNotIn('status', ['canceled', 'cancelled'])
        ->sum('net_amount');
        
    $purchasesSum = (float) DB::table('invoices')
        ->where('user_id', $userId)
        ->where('company_id', $companyId)
        ->whereIn('invoice_type_code', ['purchase'])
        ->whereNotIn('status', ['canceled', 'cancelled'])
        ->sum('net_amount');
        
    // Payments
    $paymentsSum = (float) DB::table('payments')
        ->where('user_id', $userId)
        ->where('company_id', $companyId)
        ->sum('amount');
        
    if ($relType === 'receivable') {
        $expectedBalance = $openingBalance + $salesSum - $purchasesSum - $paymentsSum;
    } else {
        $expectedBalance = $openingBalance + $purchasesSum - $salesSum - $paymentsSum;
    }
    
    // Ledger Balance
    // A user's ledger balance should include entries where:
    // 1. source is User
    // 2. sub_account is User
    // 3. source is Invoice (belonging to User)
    // 4. source is Payment (belonging to User)
    
    $ledgerDebitsQuery = DB::table('financial_ledger')
        ->where('company_id', $companyId)
        ->where('type', 'debit')
        ->where(function($q) use ($userId, $userInvoices, $userPayments) {
            $q->where(function($q1) use ($userId) {
                $q1->where('source_type', 'App\Models\User')->where('source_id', $userId);
            })->orWhere(function($q2) use ($userId) {
                $q2->where('sub_account_type', 'App\Models\User')->where('sub_account_id', $userId);
            });
            
            if (!empty($userInvoices)) {
                $q->orWhere(function($q3) use ($userInvoices) {
                    $q3->where('source_type', 'Modules\Sales\Models\Invoice')->whereIn('source_id', $userInvoices);
                });
            }
            if (!empty($userPayments)) {
                $q->orWhere(function($q4) use ($userPayments) {
                    $q4->where('source_type', 'App\Models\Payment')->whereIn('source_id', $userPayments); // Guessing payment model
                });
            }
        });
        
    $ledgerDebits = (float) $ledgerDebitsQuery->sum('amount');
        
    $ledgerCreditsQuery = DB::table('financial_ledger')
        ->where('company_id', $companyId)
        ->where('type', 'credit')
        ->where(function($q) use ($userId, $userInvoices, $userPayments) {
            $q->where(function($q1) use ($userId) {
                $q1->where('source_type', 'App\Models\User')->where('source_id', $userId);
            })->orWhere(function($q2) use ($userId) {
                $q2->where('sub_account_type', 'App\Models\User')->where('sub_account_id', $userId);
            });
            
            if (!empty($userInvoices)) {
                $q->orWhere(function($q3) use ($userInvoices) {
                    $q3->where('source_type', 'Modules\Sales\Models\Invoice')->whereIn('source_id', $userInvoices);
                });
            }
            if (!empty($userPayments)) {
                $q->orWhere(function($q4) use ($userPayments) {
                    $q4->where('source_type', 'App\Models\Payment')->whereIn('source_id', $userPayments);
                });
            }
        });
        
    $ledgerCredits = (float) $ledgerCreditsQuery->sum('amount');
        
    $ledgerBalance = $ledgerDebits - $ledgerCredits;
    if ($relType == 'payable') {
        $ledgerBalance = $ledgerCredits - $ledgerDebits;
    }

    $totalExpected += $expectedBalance;
    
    $diff = abs($actualBalance - $expectedBalance);
    if (round($diff, 2) > 0.01) {
        $diffCount++;
        $totalDifferences += $diff;
        
        $report .= "| {$userId} | {$companyId} | {$relType} | " . number_format($actualBalance, 2) . " | " . number_format($expectedBalance, 2) . " | " . number_format($ledgerBalance, 2) . " | " . number_format($diff, 2) . " |\n";
    } else {
        $matchedCount++;
    }
}

$report = str_replace(
    ['{checkedCount}', '{matchedCount}', '{diffCount}', '{totalActual}', '{totalExpected}', '{totalDifferences}'],
    [$checkedCount, $matchedCount, $diffCount, number_format($totalActual, 2), number_format($totalExpected, 2), number_format($totalDifferences, 2)],
    $report
);

file_put_contents(__DIR__ . '/phase5_balance_reconciliation.md', $report);
echo "Reconciliation completed. Report generated at phase5_balance_reconciliation.md\n";
