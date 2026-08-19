<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$ownerTx = \Modules\Accounting\Models\OwnerFundTransaction::with(['user', 'cashbox'])->first();
echo "OwnerFundTransaction:\n";
echo json_encode($ownerTx, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n\n";

$custody = \Modules\Accounting\Models\Custody::with(['user', 'cashbox'])->first();
echo "Custody:\n";
echo json_encode($custody, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n\n";