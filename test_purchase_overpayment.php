<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\User;
use App\Models\Invoice;
use App\Services\InvoicePaymentHandler;
use Illuminate\Support\Facades\Auth;

$buyer = User::find(1); // Auth User (Company)
$supplier = User::find(3); // Supplier
Auth::login($buyer);

$invoice = Invoice::find(16); // Reusing invoice object for metadata

$oldBalance = $supplier->getDefaultCashBoxForCompany(1)->balance;

// Purchase overpayment: Paid 1500 for a 1000 net.
// remaining_amount = 1000 - 1500 = -500.
// Supplier balance (how much we owe them) should decrease by 500.
$paidAmount = 1500;
$remainingAmount = -500;

echo "Testing Purchase Overpayment: Paid=1500 Net=1000. Balance diff should be -500\n";

$handler = new InvoicePaymentHandler();
$handler->handlePurchasePayment($invoice, $buyer, $supplier, $paidAmount, $remainingAmount);

$newBalance = $supplier->getDefaultCashBoxForCompany(1)->balance;

echo "Old Balance: $oldBalance\n";
echo "New Balance: $newBalance\n";
echo "Difference: " . ($newBalance - $oldBalance) . "\n";
