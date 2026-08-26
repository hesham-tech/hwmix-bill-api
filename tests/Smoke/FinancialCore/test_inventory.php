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
use Modules\Inventory\Models\Stock;
use App\Services\PurchaseInvoiceService;
use App\Services\SaleInvoiceService;
use App\Services\ReturnService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

try {
    DB::beginTransaction();

    $companyId = 35; // default test company
    $branchId = 1;

    // Login to avoid created_by null
    Auth::loginUsingId(1);

    $product = Product::first();
    $variant = ProductVariant::first();

    // Ensure stock
    $stock = Stock::firstOrCreate([
        'variant_id' => $variant->id,
        'company_id' => $companyId,
    ], [
        'quantity' => 0,
        'cost' => 100,
        'warehouse_id' => 1,
    ]);

    $initialQuantity = Stock::where('variant_id', $variant->id)->sum('quantity');
    echo "Initial Stock Quantity: {$initialQuantity}\n";

    $supplierId = User::first()->id ?? 1;
    $customerId = User::first()->id ?? 1;

    $purchaseInvoiceType = InvoiceType::where('code', 'purchase')->first();
    $saleInvoiceType = InvoiceType::where('code', 'sale')->first();
    $returnInvoiceType = InvoiceType::where('code', 'sale_return')->first();
    
    $purchaseService = app(PurchaseInvoiceService::class);
    $saleService = app(SaleInvoiceService::class);
    $returnService = app(ReturnService::class);

    echo "\n--- 1. Testing Purchase (Stock +10) ---\n";
    $purchaseData = [
        'company_id' => $companyId,
        'branch_id' => $branchId,
        'user_id' => $supplierId,
        'invoice_type_id' => $purchaseInvoiceType->id,
        'payment_method' => 'credit',
        'cash_box_id' => 1,
        'user_cash_box_id' => 1,
        'status' => 'confirmed',
        'items' => [
            [
                'variant_id' => $variant->id,
                'product_id' => $product->id,
                'name' => 'Test Item',
                'quantity' => 10,
                'unit_price' => 100, // 1000 total
                'tax_amount' => 0,
                'discount' => 0,
                'total' => 1000,
                'warehouse_id' => 1,
            ]
        ],
        'paid_amount' => 0,
        'tax_rate' => 0,
        'discount_amount' => 0,
        'warehouse_id' => 1,
    ];
    $purchaseInvoice = $purchaseService->create($purchaseData);
    
    $stockQuantityAfterPurchase = Stock::where('variant_id', $variant->id)->sum('quantity');
    echo "Expected: " . ($initialQuantity + 10) . ", Actual: {$stockQuantityAfterPurchase}\n";
    if ($stockQuantityAfterPurchase != ($initialQuantity + 10)) {
        throw new \Exception("Purchase did not increase stock correctly.");
    }
    echo "PASS: Purchase updated stock.\n";

    echo "\n--- 2. Testing Sale (Stock -5) ---\n";
    $saleData = [
        'company_id' => $companyId,
        'branch_id' => $branchId,
        'user_id' => $customerId,
        'invoice_type_id' => $saleInvoiceType->id,
        'payment_method' => 'credit',
        'cash_box_id' => 1,
        'user_cash_box_id' => 1,
        'status' => 'confirmed',
        'items' => [
            [
                'variant_id' => $variant->id,
                'product_id' => $product->id,
                'name' => 'Test Item',
                'quantity' => 5,
                'unit_price' => 150, // 750 total
                'tax_amount' => 0,
                'discount' => 0,
                'total' => 750,
                'warehouse_id' => 1,
            ]
        ],
        'paid_amount' => 0,
        'tax_rate' => 0,
        'discount_amount' => 0,
        'warehouse_id' => 1,
    ];
    $saleInvoice = $saleService->create($saleData);
    
    $stockQuantityAfterSale = Stock::where('variant_id', $variant->id)->sum('quantity');
    echo "Expected: " . ($initialQuantity + 5) . ", Actual: {$stockQuantityAfterSale}\n";
    if ($stockQuantityAfterSale != ($initialQuantity + 5)) {
        throw new \Exception("Sale did not decrease stock correctly.");
    }
    echo "PASS: Sale updated stock.\n";

    echo "\n--- 3. Testing Return (Stock +2) ---\n";
    $returnData = [
        'company_id' => $companyId,
        'branch_id' => $branchId,
        'user_id' => $customerId,
        'invoice_type_id' => $returnInvoiceType->id,
        'parent_invoice_id' => $saleInvoice->id,
        'payment_method' => 'credit',
        'cash_box_id' => 1,
        'user_cash_box_id' => 1,
        'status' => 'confirmed',
        'items' => [
            [
                'variant_id' => $variant->id,
                'product_id' => $product->id,
                'name' => 'Test Item',
                'quantity' => 2,
                'unit_price' => 150, // 300 total
                'tax_amount' => 0,
                'discount' => 0,
                'total' => 300,
                'warehouse_id' => 1,
            ]
        ],
        'paid_amount' => 0,
        'tax_rate' => 0,
        'discount_amount' => 0,
        'warehouse_id' => 1,
    ];
    $returnInvoice = $returnService->create($returnData);
    
    $stockQuantityAfterReturn = Stock::where('variant_id', $variant->id)->sum('quantity');
    echo "Expected: " . ($initialQuantity + 7) . ", Actual: {$stockQuantityAfterReturn}\n";
    if ($stockQuantityAfterReturn != ($initialQuantity + 7)) {
        throw new \Exception("Return did not increase stock correctly.");
    }
    echo "PASS: Return updated stock.\n";

    echo "\n--- 4. Testing Sale Cancellation (Stock +3) ---\n";
    $saleData2 = $saleData;
    $saleData2['invoice_number'] = 'SALE-' . time() . rand(10,99);
    $saleData2['items'][0]['quantity'] = 4;
    $saleData2['items'][0]['total'] = 600;
    $saleData2['paid_amount'] = 600;
    $saleInvoice2 = $saleService->create($saleData2);
    
    $stockQuantityAfterSale2 = Stock::where('variant_id', $variant->id)->sum('quantity');
    echo "Stock after second sale (qty 4): {$stockQuantityAfterSale2}\n";

    // Cancel sale 2
    $saleService->cancel($saleInvoice2);
    
    $stockQuantityAfterCancel = Stock::where('variant_id', $variant->id)->sum('quantity');
    echo "Stock after cancel: {$stockQuantityAfterCancel}\n";
    
    if ($stockQuantityAfterCancel != $stockQuantityAfterSale2 + 4) {
        throw new \Exception("Cancel did not increase stock correctly.");
    }
    echo "PASS: Cancel updated stock.\n";


    DB::rollBack();
    echo "\nTEST COMPLETED SUCCESSFULLY\n";
} catch (\Exception $e) {
    DB::rollBack();
    echo "\nTEST FAILED: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString();
}
