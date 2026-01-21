<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\InvoiceType;
use App\Models\Product;
use App\Models\Invoice;
use App\Models\User;
use App\Models\Company;

echo "--- Database Status ---\n";
echo "InvoiceTypes: " . InvoiceType::count() . "\n";
echo "Products: " . Product::count() . "\n";
echo "Invoices: " . Invoice::count() . "\n";
echo "Users: " . User::count() . "\n";
echo "Companies: " . Company::count() . "\n";
echo "--- Tables List ---\n";
$tables = Illuminate\Support\Facades\DB::select('SHOW TABLES');
foreach ($tables as $table) {
    echo current((array) $table) . "\n";
}
