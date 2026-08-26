<?php
// test_phase5_auditor.php

$host = '127.0.0.1';
$db   = 'hwnix_prod_trial';
$user = 'root';
$pass = '';
$charset = 'utf8mb4';

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
];

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
} catch (\PDOException $e) {
    throw new \PDOException($e->getMessage(), (int)$e->getCode());
}

$report = "# Phase 5: Historical Data Audit Report\n\n";
$report .= "Date of Audit: " . date('Y-m-d H:i:s') . "\n\n";

$tables = [
    'Invoices' => 'invoices',
    'Payments' => 'payments',
    'Transactions' => 'transactions',
    'Financial Ledger' => 'financial_ledger',
    'Stakeholder Balances (Receivables)' => 'stakeholder_financial_balances',
    'Business Relations' => 'business_relations',
    'Cash Boxes (Treasuries)' => 'cash_boxes',
    'Stocks (Inventory)' => 'stocks',
    'Installment Plans' => 'installment_plans',
    'Installments' => 'installments',
    'Installment Payments' => 'installment_payments',
];

$report .= "## 1. Overall Record Counts\n\n";
$report .= "| Table | Total Records |\n";
$report .= "|---|---|\n";

foreach ($tables as $name => $table) {
    try {
        $stmt = $pdo->query("SELECT COUNT(*) as cnt FROM $table");
        $count = $stmt->fetch()['cnt'];
        $report .= "| $name ($table) | " . number_format($count) . " |\n";
    } catch (\Exception $e) {
        $report .= "| $name ($table) | Error / Not Found |\n";
    }
}
$report .= "\n";

$report .= "## 2. Old Logic vs New Logic (Ledger Integration)\n\n";
$report .= "New logic is characterized by operations having corresponding entries in the `financial_ledger`.\n\n";

// Invoices
$stmt = $pdo->query("SELECT COUNT(*) as cnt FROM invoices i WHERE EXISTS (SELECT 1 FROM financial_ledger fl WHERE fl.source_id = i.id AND fl.source_type LIKE '%Invoice%')");
$new_invoices = $stmt->fetch()['cnt'];

$stmt = $pdo->query("SELECT COUNT(*) as cnt FROM invoices i WHERE NOT EXISTS (SELECT 1 FROM financial_ledger fl WHERE fl.source_id = i.id AND fl.source_type LIKE '%Invoice%')");
$old_invoices = $stmt->fetch()['cnt'];

$report .= "### Invoices\n";
$report .= "- **With Ledger (New Logic):** " . number_format($new_invoices) . "\n";
$report .= "- **Without Ledger (Old Logic):** " . number_format($old_invoices) . "\n\n";

// Payments
$stmt = $pdo->query("SELECT COUNT(*) as cnt FROM payments p WHERE EXISTS (SELECT 1 FROM financial_ledger fl WHERE fl.source_id = p.id AND fl.source_type LIKE '%Payment%')");
$new_payments = $stmt->fetch()['cnt'];

$stmt = $pdo->query("SELECT COUNT(*) as cnt FROM payments p WHERE NOT EXISTS (SELECT 1 FROM financial_ledger fl WHERE fl.source_id = p.id AND fl.source_type LIKE '%Payment%')");
$old_payments = $stmt->fetch()['cnt'];

$report .= "### Payments\n";
$report .= "- **With Ledger (New Logic):** " . number_format($new_payments) . "\n";
$report .= "- **Without Ledger (Old Logic):** " . number_format($old_payments) . "\n\n";

// Transactions
$stmt = $pdo->query("SELECT COUNT(*) as cnt FROM transactions t WHERE EXISTS (SELECT 1 FROM financial_ledger fl WHERE fl.source_id = t.id AND fl.source_type LIKE '%Transaction%')");
$new_transactions = $stmt->fetch()['cnt'];

$stmt = $pdo->query("SELECT COUNT(*) as cnt FROM transactions t WHERE NOT EXISTS (SELECT 1 FROM financial_ledger fl WHERE fl.source_id = t.id AND fl.source_type LIKE '%Transaction%')");
$old_transactions = $stmt->fetch()['cnt'];

$report .= "### Transactions\n";
$report .= "- **With Ledger (New Logic):** " . number_format($new_transactions) . "\n";
$report .= "- **Without Ledger (Old Logic):** " . number_format($old_transactions) . "\n\n";

// Returns (Invoices where type suggests return)
$report .= "### Returns (Based on Invoice Type)\n";
try {
    $stmt = $pdo->query("SELECT COUNT(*) as cnt FROM invoices i JOIN invoice_types it ON i.invoice_type_id = it.id WHERE it.code LIKE '%return%' OR i.invoice_type_code LIKE '%return%'");
    $return_count = $stmt->fetch()['cnt'];
    $report .= "- **Total Return Invoices:** " . number_format($return_count) . "\n\n";
} catch (\Exception $e) {
    $report .= "- Error retrieving returns data.\n\n";
}

file_put_contents('phase5_historical_audit.md', $report);

echo "Audit completed. Report generated at phase5_historical_audit.md\n";
