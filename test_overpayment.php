<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\User;
use App\Models\Invoice;
use App\Services\InvoicePaymentHandler;
use Illuminate\Support\Facades\Auth;

$seller = User::find(1);
$buyer = User::find(5);
Auth::login($seller);

$invoice = Invoice::find(16);
if (!$invoice)
    die("No invoice 16");

$oldBalance = $buyer->getDefaultCashBoxForCompany(1)->balance;

// Force negative remaining amount for test
$paidAmount = $invoice->net_amount + 500;
$remainingAmount = -500;

echo "Testing Sale Overpayment: Paid=" . $paidAmount . " Net=" . $invoice->net_amount . " Balance diff should be +500\n";

$handler = new InvoicePaymentHandler();
$handler->handleSalePayment($invoice, $seller, $buyer, $paidAmount, $remainingAmount);

$newBalance = $buyer->getDefaultCashBoxForCompany(1)->balance;

echo "Old Balance: $oldBalance\n";
echo "New Balance: $newBalance\n";
echo "Difference: " . ($newBalance - $oldBalance) . "\n";
