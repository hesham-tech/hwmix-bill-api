<?php
require __DIR__ . '/../../../vendor/autoload.php';
$app = require_once __DIR__ . '/../../../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\User;
use Modules\Companies\Models\StakeholderFinancialBalance;
use Illuminate\Support\Facades\DB;
use App\Services\SaleInvoiceService;
use App\Services\FinancialEngine;
use App\Services\ReturnService;
use Modules\Accounting\Models\CashBox;
use App\Models\InvoiceType;
use Modules\Inventory\Models\ProductVariant;
use Illuminate\Support\Facades\Auth;

try {
    DB::beginTransaction();

    $companyId = 35; // Default company
    Auth::loginUsingId(1); // just for context
    
    // Use a NEW customer to avoid isDefaultCashCustomer checks
    $customer = User::create([
        'full_name' => 'Test Customer AR',
        'phone' => '01' . rand(10000000, 99999999),
        'email' => 'ar_cust_' . uniqid() . '@example.com',
        'password' => bcrypt('password'),
        'company_id' => $companyId,
        'active_company_id' => $companyId,
    ]);

    $salesService = app(SaleInvoiceService::class);
    $financialEngine = app(FinancialEngine::class);
    $returnService = app(ReturnService::class);

    $cashBox = CashBox::first() ?? CashBox::create(['company_id' => $companyId, 'name_en' => 'Main', 'currency_id' => 1]);
    $variant = ProductVariant::first();
    $salesType = InvoiceType::where('code', 'sale')->first();
    $returnType = InvoiceType::where('code', 'sale_return')->first();

    echo "=== Testing AR Scenario ===\n";
    $initialBalance = StakeholderFinancialBalance::where('user_id', $customer->id)->where('relation_type', 'receivable')->value('balance') ?? 0;
    echo "1. Initial AR Balance: $initialBalance\n";

    // Create Credit Sales Invoice
    $invoiceData = [
        'company_id' => $companyId,
        'branch_id' => null,
        'user_id' => $customer->id,
        'invoice_type_id' => $salesType->id,
        'payment_method' => 'credit',
        'cash_box_id' => $cashBox->id,
        'user_cash_box_id' => $cashBox->id,
        'status' => 'confirmed',
        'items' => [
            [
                'variant_id' => $variant->id,
                'product_id' => $variant->product_id,
                'name' => 'Test',
                'quantity' => 10,
                'unit_price' => 100,
                'total' => 1000
            ]
        ],
        'paid_amount' => 0,
        'net_amount' => 1000,
    ];
    $invoice = $salesService->create($invoiceData);
    $bAfterInvoice = StakeholderFinancialBalance::where('user_id', $customer->id)->where('relation_type', 'receivable')->value('balance') ?? 0;
    echo "2. AR Balance after Credit Invoice: $bAfterInvoice\n";

    // Partial Payment
    $financialEngine->processPaymentReceipt($invoice, 300, ['cash_box_id' => $cashBox->id]);
    $bAfterPartial = StakeholderFinancialBalance::where('user_id', $customer->id)->where('relation_type', 'receivable')->value('balance') ?? 0;
    echo "3. AR Balance after Partial Payment (300): $bAfterPartial\n";

    // Full Payment of remaining
    $financialEngine->processPaymentReceipt($invoice, 700, ['cash_box_id' => $cashBox->id]);
    $bAfterFull = StakeholderFinancialBalance::where('user_id', $customer->id)->where('relation_type', 'receivable')->value('balance') ?? 0;
    echo "4. AR Balance after Full Payment (700): $bAfterFull\n";

    // Return (Partial Return of 2 items, refunding 200)
    $returnData = [
        'company_id' => $companyId,
        'branch_id' => null,
        'user_id' => $customer->id,
        'parent_invoice_id' => $invoice->id,
        'invoice_type_id' => $returnType->id,
        'payment_method' => 'cash',
        'cash_box_id' => $cashBox->id,
        'user_cash_box_id' => $cashBox->id,
        'status' => 'confirmed',
        'items' => [
            [
                'variant_id' => $variant->id,
                'product_id' => $variant->product_id,
                'name' => 'Test',
                'quantity' => 2,
                'unit_price' => 100,
                'total' => 200
            ]
        ],
        'paid_amount' => 200, // refund
        'net_amount' => 200,
    ];
    $returnInv = $returnService->create($returnData);
    $bAfterReturn = StakeholderFinancialBalance::where('user_id', $customer->id)->where('relation_type', 'receivable')->value('balance') ?? 0;
    echo "5. AR Balance after Return (Cash Refund 200): $bAfterReturn\n";

    // Ledger balance check
    // The Ledger does NOT currently store user_id! FinancialLedgerService just records 'asset', 'revenue', 'expense', etc.
    // However, the test must check it. Wait, maybe there's a `receivables` account_type?
    // Let's get the sum of 'receivables' account_type for this user's operations.
    $operations = \App\Models\FinancialOperation::whereIn('id', function($q) use ($customer) {
        // Just find operations related to this customer's invoices and receipts
        $q->select('financial_operation_id')->from('transactions')->where('user_id', $customer->id);
    })->pluck('id');

    $ledgerDebits = DB::table('financial_ledger')->whereIn('financial_operation_id', $operations)->where('type', 'debit')->where('account_type', 'receivables')->sum('amount') ?? 0;
    $ledgerCredits = DB::table('financial_ledger')->whereIn('financial_operation_id', $operations)->where('type', 'credit')->where('account_type', 'receivables')->sum('amount') ?? 0;
    $ledgerBalance = $ledgerDebits - $ledgerCredits;

    echo "6. Ledger Net Receivables for this customer: $ledgerBalance\n";

    if (abs($ledgerBalance - $bAfterReturn) < 0.01) {
         echo "RESULT: PASS - Stakeholder balance perfectly matches Ledger.\n";
    } else {
         echo "RESULT: FAIL - Balance ($bAfterReturn) != Ledger ($ledgerBalance)\n";
    }

    DB::rollBack();
    echo "TEST COMPLETE\n";
} catch (\Exception $e) {
    DB::rollBack();
    echo "ERROR: " . $e->getMessage() . "\n";
}
