<?php
require __DIR__ . '/../../../vendor/autoload.php';
$app = require_once __DIR__ . '/../../../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\User;
use App\Models\Invoice;
use App\Models\InvoiceType;
use Modules\Inventory\Models\Product;
use Modules\Inventory\Models\ProductVariant;
use App\Services\PurchaseInvoiceService;
use Modules\Accounting\Models\Ledger;
use Modules\Accounting\Models\LedgerLine;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

try {
    DB::beginTransaction();

    $companyId = 35; // default test company
    $branchId = 1;

    // Login to avoid created_by null
    $u = User::find(1);
    $u->active_company_id = 35;
    $u->save();
    Auth::login($u);

    // Use existing supplier and variant
    $supplierId = User::first()->id ?? 1;
    $variantId = ProductVariant::first()->id ?? 1;

    $invoiceType = InvoiceType::where('code', 'purchase')->first();
    if (!$invoiceType) {
        throw new \Exception("Purchase invoice type not found!");
    }

    $service = app(PurchaseInvoiceService::class);

    echo "--- 1. Testing Full Cash Purchase Invoice ---\n";
    $cashInvoiceData = [
        'company_id' => $companyId,
        'branch_id' => $branchId,
        'user_id' => $supplierId,
        'invoice_type_id' => $invoiceType->id,
        'payment_method' => 'cash',
        'cash_box_id' => 1,
        'user_cash_box_id' => 1,
        'status' => 'paid',
        'items' => [
            [
                'variant_id' => $variantId,
                'product_id' => 1,
                'name' => 'Test Item',
                'quantity' => 10,
                'unit_price' => 100, // 1000 total
                'tax_amount' => 0,
                'discount' => 0,
                'total' => 1000
            ]
        ],
        'paid_amount' => 1000,
        'tax_rate' => 0,
        'discount_amount' => 0,
    ];
    $cashInvoice = $service->create($cashInvoiceData);
    echo "Created Cash Invoice ID: {$cashInvoice->id}\n";
    

    echo "\n--- 2. Testing Credit (Deferred) Purchase Invoice ---\n";
    $creditInvoiceData = [
        'company_id' => $companyId,
        'branch_id' => $branchId,
        'user_id' => $supplierId,
        'invoice_type_id' => $invoiceType->id,
        'payment_method' => 'credit',
        'cash_box_id' => 1,
        'user_cash_box_id' => 1,
        'status' => 'confirmed',
        'items' => [
            [
                'variant_id' => $variantId,
                'product_id' => 1,
                'name' => 'Test Item',
                'quantity' => 5,
                'unit_price' => 100, // 500 total
                'tax_amount' => 0,
                'discount' => 0,
                'total' => 500
            ]
        ],
        'paid_amount' => 0,
        'tax_rate' => 0,
        'discount_amount' => 0,
    ];
    $creditInvoice = $service->create($creditInvoiceData);
    echo "Created Credit Invoice ID: {$creditInvoice->id}\n";
    


    echo "\n--- 3. Testing Partial Payment Purchase Invoice ---\n";
    $partialInvoiceData = [
        'company_id' => $companyId,
        'branch_id' => $branchId,
        'user_id' => $supplierId,
        'invoice_type_id' => $invoiceType->id,
        'payment_method' => 'cash',
        'cash_box_id' => 1,
        'user_cash_box_id' => 1, // Or split?
        'status' => 'partially_paid',
        'items' => [
            [
                'variant_id' => $variantId,
                'product_id' => 1,
                'name' => 'Test Item',
                'quantity' => 5,
                'unit_price' => 100, // 500 total
                'tax_amount' => 0,
                'discount' => 0,
                'total' => 500
            ]
        ],
        'paid_amount' => 200,
        'tax_rate' => 0,
        'discount_amount' => 0,
    ];
    $partialInvoice = $service->create($partialInvoiceData);
    echo "Created Partial Invoice ID: {$partialInvoice->id}\n";
    


    echo "\n--- 4. Testing Cancel Purchase Invoice ---\n";
    // We can cancel the credit invoice since it's not fully paid
    $service->cancel($creditInvoice);
    echo "Cancelled Credit Invoice ID: {$creditInvoice->id}\n";
    
    // Check ledger entries commented out
    $ledgerLines = \Modules\Accounting\Models\FinancialLedger::where('source_type', 'App\Models\Invoice')
          ->where('source_id', $creditInvoice->id)
          ->get();
    echo "Ledger Lines count: " . $ledgerLines->count() . "\n";

    DB::rollBack();
    echo "\nTEST COMPLETED SUCCESSFULLY\n";
} catch (\Exception $e) {
    DB::rollBack();
    echo "\nTEST FAILED: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString();
}
