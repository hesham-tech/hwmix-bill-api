<?php

require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\CashBox;
use App\Models\Transaction;
use Illuminate\Support\Facades\DB;

$reportFile = __DIR__ . '/phase5_cashbox_reconciliation.md';

$report = "# Phase 5: CashBox Reconciliation Report\n\n";
$report .= "Generated at: " . date('Y-m-d H:i:s') . "\n\n";
$report .= "| CashBox ID | Name | Opening | Receipts | Payments | Transfer In | Transfer Out | Expected | Actual | Diff |\n";
$report .= "|------------|------|---------|----------|----------|-------------|--------------|----------|--------|------|\n";

$cashBoxes = CashBox::withoutGlobalScopes()->get();
$totalExpected = 0;
$totalActual = 0;
$totalDiff = 0;

foreach($cashBoxes as $box) {
    $openingCash = 0;
    
    // Check if there is an explicit opening balance deposit
    $openingTx = Transaction::where('cashbox_id', $box->id)
        ->where('type', 'deposit')
        ->where('description', 'like', '%رصيد افتتاحي%')
        ->first();
        
    if ($openingTx) {
        $openingCash = (float)$openingTx->amount;
    } else {
        // If no explicit opening balance transaction, use balance_before of first transaction
        $firstTx = Transaction::where('cashbox_id', $box->id)->orderBy('id', 'asc')->first();
        if ($firstTx) {
            $openingCash = (float)$firstTx->balance_before;
        }
    }
    
    $receiptsQuery = Transaction::where('cashbox_id', $box->id)->whereIn('type', ['deposit', 'receipt']);
    if ($openingTx) {
        $receiptsQuery->where('id', '!=', $openingTx->id);
    }
    $receipts = (float)$receiptsQuery->sum('amount');
    
    $payments = (float)Transaction::where('cashbox_id', $box->id)->whereIn('type', ['withdraw', 'payment', 'expense'])->sum('amount');
    $transferIn = (float)Transaction::where('cashbox_id', $box->id)->where('type', 'transfer_in')->sum('amount');
    $transferOut = (float)Transaction::where('cashbox_id', $box->id)->where('type', 'transfer_out')->sum('amount');
    
    $expected = $openingCash + $receipts - $payments + $transferIn - $transferOut;
    $actual = (float)$box->balance;
    $diff = $expected - $actual;
    
    $totalExpected += $expected;
    $totalActual += $actual;
    $totalDiff += $diff;
    
    $report .= "| {$box->id} | {$box->name} | {$openingCash} | {$receipts} | {$payments} | {$transferIn} | {$transferOut} | {$expected} | {$actual} | {$diff} |\n";
}

$report .= "| **TOTAL** | | | | | | | **{$totalExpected}** | **{$totalActual}** | **{$totalDiff}** |\n";

file_put_contents($reportFile, $report);

echo "Reconciliation complete. See phase5_cashbox_reconciliation.md\n";
