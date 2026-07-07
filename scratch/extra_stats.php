<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();
use Illuminate\Support\Facades\DB;

echo "cash_boxes without branch_id: " . DB::table('cash_boxes')->whereNull('branch_id')->count() . PHP_EOL;
echo "invoices with cashbox_id: " . DB::table('invoices')->whereNotNull('cash_box_id')->count() . PHP_EOL;
echo "installment_payments: " . DB::table('installment_payments')->count() . PHP_EOL;
echo "payments table: " . DB::table('payments')->count() . PHP_EOL;
echo "invoice_payments: " . DB::table('invoice_payments')->count() . PHP_EOL;
echo "transactions with source_invoice_id: " . DB::table('transactions')->whereNotNull('source_invoice_id')->count() . PHP_EOL;
echo "transactions with target_user_id: " . DB::table('transactions')->whereNotNull('target_user_id')->count() . PHP_EOL;
echo "stats_users_summary: " . DB::table('stats_users_summary')->count() . PHP_EOL;
echo "stats_products_summary: " . DB::table('stats_products_summary')->count() . PHP_EOL;
