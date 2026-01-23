<?php

use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\DailySalesSummary;
use App\Models\InvoiceType;
use Illuminate\Support\Facades\DB;

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$date = '2026-01-23';
$companyId = 1;

DB::enableQueryLog();

echo "Running Revenue Query...\n";
$invoiceStats = Invoice::query()
    ->where('company_id', $companyId)
    ->where(function ($q) use ($date) {
        $q->whereDate('issue_date', $date)
            ->orWhere(fn($q2) => $q2->whereNull('issue_date')->whereDate('created_at', $date));
    })
    ->whereIn('status', ['confirmed', 'paid', 'partially_paid'])
    ->whereHas('invoiceType', fn($q) => $q->whereIn('code', ['sale', 'service', 'installment_sale', 'sale_return']))
    ->selectRaw('
        SUM(CASE 
            WHEN EXISTS (SELECT 1 FROM invoice_types WHERE id = invoices.invoice_type_id AND code = "sale_return") 
            THEN -net_amount 
            ELSE net_amount 
        END) as revenue,
        COUNT(*) as count
    ')
    ->first();

echo "Revenue: " . ($invoiceStats->revenue ?? 0) . " | Count: " . ($invoiceStats->count ?? 0) . "\n";

echo "Running COGS Query...\n";
$cogs = DB::table('invoice_items')
    ->join('invoices', 'invoice_items.invoice_id', '=', 'invoices.id')
    ->join('invoice_types', 'invoices.invoice_type_id', '=', 'invoice_types.id')
    ->where('invoices.company_id', $companyId)
    ->where(function ($q) use ($date) {
        $q->whereDate('invoices.issue_date', $date)
            ->orWhere(fn($q2) => $q2->whereNull('invoices.issue_date')->whereDate('invoices.created_at', $date));
    })
    ->whereIn('invoices.status', ['confirmed', 'paid', 'partially_paid'])
    ->whereIn('invoice_types.code', ['sale', 'installment_sale', 'sale_return'])
    ->sum(DB::raw('
        CASE 
            WHEN invoice_types.code = "sale_return" 
            THEN -invoice_items.total_cost 
            ELSE invoice_items.total_cost 
        END
    '));

echo "COGS: " . $cogs . "\n";

print_r(DB::getQueryLog());

echo "\n--- Individual items for matching invoices ---\n";
$items = DB::table('invoice_items')
    ->join('invoices', 'invoice_items.invoice_id', '=', 'invoices.id')
    ->join('invoice_types', 'invoices.invoice_type_id', '=', 'invoice_types.id')
    ->where('invoices.company_id', $companyId)
    ->where(function ($q) use ($date) {
        $q->whereDate('invoices.issue_date', $date)
            ->orWhere(fn($q2) => $q2->whereNull('invoices.issue_date')->whereDate('invoices.created_at', $date));
    })
    ->select('invoice_items.id', 'invoice_items.invoice_id', 'invoice_items.total_cost', 'invoice_types.code')
    ->get();

foreach ($items as $item) {
    echo "Item ID: {$item->id} | Inv ID: {$item->invoice_id} | TotalCost: {$item->total_cost} | Type: {$item->code}\n";
}
