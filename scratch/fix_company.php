<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$updated = Illuminate\Support\Facades\DB::table('users')
    ->whereNull('active_company_id')
    ->update(['active_company_id' => 1]);

echo "Updated $updated users with active_company_id = 1\n";

$users = Illuminate\Support\Facades\DB::table('users')->select('id','email','active_company_id')->get();
foreach ($users as $u) {
    echo "ID:{$u->id} | {$u->email} | company:{$u->active_company_id}\n";
}
