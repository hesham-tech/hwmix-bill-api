<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;
use App\Models\Company;

echo "=== COMPANIES LIST ===\n";
$companies = Company::withoutGlobalScopes()->get(['id', 'name']);
foreach ($companies as $c) {
    echo "ID: {$c->id} | Name: {$c->name}\n";
}

echo "\n=== HWNIX CASH TABLES RECORD COUNT BY COMPANY ===\n";
$tables = [
    'hwnix_cash_devices',
    'hwnix_cash_lines',
    'hwnix_cash_messages',
    'hwnix_cash_message_sources',
    'hwnix_cash_financial_accounts',
    'hwnix_cash_wallet_transactions',
    'hwnix_cash_device_settings',
    'hwnix_cash_device_commands',
    'hwnix_cash_device_heartbeats',
    'hwnix_cash_sms_analysis_results'
];

foreach ($tables as $t) {
    if (\Illuminate\Support\Facades\Schema::hasTable($t)) {
        if (\Illuminate\Support\Facades\Schema::hasColumn($t, 'company_id')) {
            $counts = DB::table($t)->select('company_id', DB::raw('count(*) as total'))->groupBy('company_id')->get();
            echo "Table: {$t}\n";
            foreach ($counts as $cnt) {
                echo "  -> Company ID {$cnt->company_id}: {$cnt->total} records\n";
            }
        } else {
            echo "Table: {$t} (No company_id column)\n";
        }
    } else {
        echo "Table: {$t} (NOT FOUND)\n";
    }
}
