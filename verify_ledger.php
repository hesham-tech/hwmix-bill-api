<?php

use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\FinancialLedger;
use App\Models\Company;
use App\Models\CashBox;
use Illuminate\Support\Facades\Auth;

// Mock Auth
$user = \App\Models\User::first();
Auth::login($user);

$company = Company::first();
$category = ExpenseCategory::where('company_id', $company->id)->first();
$cashBox = CashBox::where('company_id', $company->id)->first();

echo "--- Testing Expense Ledger Recording ---\n";

$expense = Expense::create([
    'expense_category_id' => $category->id,
    'amount' => 500,
    'expense_date' => now(),
    'payment_method' => 'cash',
    'cash_box_id' => $cashBox?->id,
    'notes' => 'Test Expense for Ledger',
    'company_id' => $company->id
]);

echo "Expense Created: ID {$expense->id}, Amount {$expense->amount}\n";

$ledgerEntries = FinancialLedger::where('source_type', Expense::class)
    ->where('source_id', $expense->id)
    ->get();

echo "Ledger Entries Found: " . $ledgerEntries->count() . "\n";

foreach ($ledgerEntries as $entry) {
    echo "Entry: {$entry->type} | Amount: {$entry->amount} | Account: {$entry->account_type} | Desc: {$entry->description}\n";
}

if ($ledgerEntries->count() === 2) {
    echo "SUCCESS: Balanced Entry Recorded.\n";
} else {
    echo "FAILED: Expected 2 entries, found " . $ledgerEntries->count() . "\n";
}

// Clean up
$expense->delete(); // Soft delete
FinancialLedger::where('source_type', Expense::class)->where('source_id', $expense->id)->delete();
