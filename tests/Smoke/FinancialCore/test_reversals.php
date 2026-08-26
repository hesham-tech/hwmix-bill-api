<?php

use App\Models\User;
use Modules\Sales\Models\Invoice;
use Modules\Sales\Models\InvoiceType;
use App\Services\FinancialEngine;
use Illuminate\Support\Facades\DB;
use App\Models\CashBox;

require 'vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

DB::beginTransaction();

try {
    $companyId = 1;
    $cashBoxId = 1;
    
    // Get existing user
    $customer = User::first();
    if (!$customer) die("No user found.");

    // Create an Invoice Type
    $invoiceType = InvoiceType::firstOrCreate([
        'code' => 'sale',
        'company_id' => $companyId
    ], [
        'name' => 'Sale',
        'context' => 'sales',
        'type' => 'out',
        'nature' => 'financial'
    ]);

    // Create an invoice
    $invoice = Invoice::create([
        'invoice_number' => 'INV-REV-' . rand(1, 1000),
        'invoice_type_code' => 'sale',
        'invoice_type_id' => $invoiceType->id,
        'company_id' => $companyId,
        'user_id' => $customer->id,
        'gross_amount' => 1000,
        'total_discount' => 0,
        'total_tax' => 0,
        'net_amount' => 1000,
        'paid_amount' => 0,
        'remaining_amount' => 1000,
        'status' => 'confirmed',
        'created_by' => 1
    ]);

    // Process invoice creation
    $engine = app(FinancialEngine::class);
    $operationId1 = $engine->processInvoiceCreation($invoice, ['cash_box_id' => $cashBoxId]);

    // Make a subsequent payment
    $operationId2 = $engine->processPaymentReceipt($invoice, 500, ['cash_box_id' => $cashBoxId]);

    // Get CashBox balance before cancel
    $cashBox = CashBox::find($cashBoxId);
    $balanceBeforeCancel = $cashBox->balance;
    echo "Cashbox before cancel: $balanceBeforeCancel\n";

    DB::enableQueryLog();

    // Now reverse the operation using SaleInvoiceService::cancel
    $saleInvoiceService = app(\Modules\Sales\Services\SaleInvoiceService::class);
    $saleInvoiceService->cancel($invoice);

    $log = DB::getQueryLog();
    foreach($log as $query) {
        if (strpos($query['query'], 'cash_boxes') !== false || strpos($query['query'], 'transactions') !== false || strpos($query['query'], 'financial_operations') !== false) {
            echo $query['query'] . " | " . json_encode($query['bindings']) . "\n";
        }
    }

    $cashBox = CashBox::find($cashBoxId);
    $balanceAfterCancel = $cashBox->balance;
    echo "Cashbox after cancel: $balanceAfterCancel\n";

    if ($balanceAfterCancel == $balanceBeforeCancel) {
        echo "BUG FOUND: Subsequent payment was NOT reversed when invoice was cancelled!\n";
    } elseif ($balanceAfterCancel < $balanceBeforeCancel) {
        echo "SUCCESS: Subsequent payment was reversed.\n";
    }

} catch (\Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString();
}

DB::rollBack();

