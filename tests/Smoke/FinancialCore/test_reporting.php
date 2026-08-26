<?php

require __DIR__ . '/../../../vendor/autoload.php';
$app = require_once __DIR__ . '/../../../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;
use App\Models\User;
use Modules\Sales\Models\Invoice;
use App\Models\FinancialLedger;

echo "=== Phase 4 Reporting Validation ===\n\n";

// 1. Ledger Balance Check
echo "1. Validating Financial Ledger (Assets vs Liabilities/Equity/Revenue/Expenses)...\n";
$ledgerNet = DB::table('financial_ledger')
    ->selectRaw("SUM(CASE WHEN type = 'debit' THEN amount ELSE -amount END) as net_debit")
    ->value('net_debit');

echo "   Net Ledger Balance (Debits - Credits): " . number_format($ledgerNet ?? 0, 2) . "\n";
if (abs($ledgerNet) < 0.01) {
    echo "   [PASS] Ledger is balanced.\n";
} else {
    echo "   [FAIL] Ledger is out of balance by " . number_format($ledgerNet, 2) . "\n";
}
echo "\n";

// 2. Statement vs AR/AP
echo "2. Validating Statements vs AR/AP (stakeholder_financial_balances)...\n";
$stakeholders = DB::table('stakeholder_financial_balances')->get();
$mismatches = 0;
foreach ($stakeholders as $sh) {
    $userId = $sh->user_id;
    $ledgers = FinancialLedger::withoutGlobalScopes()
        ->where(function($q) use ($userId) {
            $q->where('source_type', User::class)
              ->where('source_id', $userId);
        })
        ->orWhere(function($q) use ($userId) {
            $q->where('source_type', Invoice::class)
              ->whereIn('source_id', function($sub) use ($userId) {
                  $sub->select('id')->from('invoices')->where('user_id', $userId);
              })
              ->whereIn('account_type', ['asset', 'liability']);
        })
        ->get();

    $balance = 0;
    foreach ($ledgers as $ledger) {
        $amount = (float) $ledger->amount;
        $isDebit = $ledger->type === 'debit';
        if ($ledger->account_type === 'asset') {
            $balance += $isDebit ? $amount : -$amount;
        } else {
            $balance += $isDebit ? -$amount : $amount;
        }
    }

    if (abs($balance - $sh->balance) > 0.01) {
        echo "   [FAIL] User $userId mismatch: AR/AP={$sh->balance}, Statement={$balance}\n";
        $mismatches++;
    }
}
if ($mismatches === 0) {
    echo "   [PASS] All Statements match AR/AP perfectly.\n";
} else {
    echo "   [FAIL] Found $mismatches statement(s) that do not match AR/AP.\n";
}
echo "\n";

// 3. CashBox vs Transactions
echo "3. Validating CashBox Balances vs Transactions...\n";
$cashboxes = DB::table('cash_boxes')->get();
$cbMismatches = 0;

foreach ($cashboxes as $cb) {
    $txIn = DB::table('transactions')->where('cashbox_id', $cb->id)->where('type', 'in')->sum('amount');
    $txOut = DB::table('transactions')->where('cashbox_id', $cb->id)->where('type', 'out')->sum('amount');
    $txTransferOut = DB::table('transactions')->where('cashbox_id', $cb->id)->where('type', 'transfer')->sum('amount');
    $txTransferIn = DB::table('transactions')->where('target_cashbox_id', $cb->id)->where('type', 'transfer')->sum('amount');
    
    $netTx = $txIn - $txOut - $txTransferOut + $txTransferIn;
    
    if (abs($cb->balance - $netTx) > 0.01) {
        if ($cb->balance > 0 || $netTx > 0) {
            echo "   [FAIL] CashBox #{$cb->id} mismatch: Balance={$cb->balance}, Transactions Net={$netTx}\n";
            $cbMismatches++;
        }
    }
}
if ($cbMismatches === 0) {
    echo "   [PASS] All CashBoxes match Transactions perfectly.\n";
} else {
    echo "   [FAIL] Found $cbMismatches CashBox(es) that do not match Transactions.\n";
}
echo "\n";

// 4. Ledger vs Transactions Sum
echo "4. Validating Ledger vs Transactions Sum...\n";
$txSumIn = DB::table('transactions')->where('type', 'in')->sum('amount');
$txSumOut = DB::table('transactions')->where('type', 'out')->sum('amount');
// Transfers balance out...
echo "   Total Transactions IN: " . number_format($txSumIn, 2) . "\n";
echo "   Total Transactions OUT: " . number_format($txSumOut, 2) . "\n";

$ledgerSumDebit = DB::table('financial_ledger')->where('type', 'debit')->sum('amount');
$ledgerSumCredit = DB::table('financial_ledger')->where('type', 'credit')->sum('amount');
echo "   Total Ledger Debits: " . number_format($ledgerSumDebit, 2) . "\n";
echo "   Total Ledger Credits: " . number_format($ledgerSumCredit, 2) . "\n";

echo "=== Validation Complete ===\n";
