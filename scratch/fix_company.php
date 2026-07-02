<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$updated = Illuminate\Support\Facades\DB::table('users')
    ->where('email', 'admin@admin.com')
    ->update(['company_id' => 1]);

echo "Updated $updated users setting company_id = 1\n";
