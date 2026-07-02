<?php

use App\Models\CashBox;

require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';

$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$cashBox = CashBox::withoutGlobalScopes()->find(1257);
if ($cashBox) {
    echo "الخزنة 1257:\n";
    echo "Name: {$cashBox->name}\n";
    echo "User ID (owner): {$cashBox->user_id}\n";
    echo "Company ID: {$cashBox->company_id}\n";
    echo "Branch ID: " . ($cashBox->branch_id ?? 'NULL') . "\n";
} else {
    echo "الخزنة 1257 غير موجودة!\n";
}
