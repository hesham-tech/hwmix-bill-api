<?php

require __DIR__ . '/../../../vendor/autoload.php';
$app = require_once __DIR__ . '/../../../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Modules\Sales\Models\Invoice;
use Modules\Sales\Services\SaleInvoiceService;
use App\Models\User;
use Modules\Accounting\Models\CashBox;
use Illuminate\Support\Facades\DB;

$service = app(SaleInvoiceService::class);

$cashBox = CashBox::first();

// Create a new regular customer
$user = User::create([
    'name' => 'Regular Customer',
    'email' => 'regular'.uniqid().'@example.com',
    'password' => bcrypt('password'),
    'active_company_id' => $cashBox->company_id,
]);

// Ensure they have a balance model setup if needed, or FinancialEngine handles it.

function testInvoice($service, $cashBox, $user, $paidAmount, $label) {
    DB::beginTransaction();
    try {
        $data = [
            'company_id' => $cashBox->company_id,
            'user_id' => $user->id,
            'cash_box_id' => $cashBox->id,
            'invoice_type_code' => 'sale',
            'invoice_type_id' => 2,
            'created_by' => 1,
            'date' => now()->format('Y-m-d'),
            'items' => [
                [
                    'product_id' => 1,
                    'variant_id' => 1,
                    'name' => 'Test Product',
                    'quantity' => 1,
                    'unit_price' => 100,
                    'total' => 100,
                ]
            ],
            'paid_amount' => $paidAmount,
            'net_amount' => 100,
        ];
        
        $invoice = $service->create($data);
        echo "[$label] PASS: Invoice created.\n";
        
        // Find Financial Operation
        $op = \App\Models\FinancialOperation::where('source_id', $invoice->id)->first();
        if ($op) {
            $transactions = \App\Models\Transaction::where('financial_operation_id', $op->id)->get();
            echo "[$label] Transactions created: " . count($transactions) . "\n";
            foreach ($transactions as $t) {
                echo "[$label] - Type: {$t->type}, CashBox: {$t->cashbox_id}, User: {$t->user_id}, Amount: {$t->amount}\n";
            }
        } else {
            echo "[$label] No Financial Operation found!\n";
        }
        
    } catch (\Throwable $e) {
        echo "[$label] FAIL: " . $e->getMessage() . "\n";
    }
    DB::rollBack();
}

testInvoice($service, $cashBox, $user, 100, "CASH INVOICE (Full)");
testInvoice($service, $cashBox, $user, 0, "CREDIT INVOICE (Zero)");
testInvoice($service, $cashBox, $user, 50, "PARTIAL INVOICE");
