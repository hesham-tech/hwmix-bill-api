<?php

use App\Models\User;

require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';

$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$users = User::withoutGlobalScopes()->limit(15)->get();
echo "أول 15 مستخدم في النظام:\n";
foreach ($users as $u) {
    echo "ID: {$u->id} | Name: {$u->name} | Nickname: {$u->nickname} | Email: {$u->email} | Company ID: {$u->company_id} | Active Company ID: {$u->active_company_id}\n";
}
