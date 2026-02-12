<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\User;
use App\Models\CashBox;
use Illuminate\Support\Facades\DB;

$u = User::find(6);
if (!$u) {
    die("User 6 not found.\n");
}

echo "--- User ID 6 Diagnostic ---\n";
echo "Full Name: " . $u->full_name . "\n";
echo "User Table Company ID: " . ($u->company_id ?? 'NULL') . "\n";
echo "Balance Attribute: " . $u->balance . "\n";

$cbDefault = $u->getDefaultCashBoxForCompany();
echo "Default CashBox found: " . ($cbDefault ? 'ID ' . $cbDefault->id . ' (Co: ' . $cbDefault->company_id . ', Bal: ' . $cbDefault->balance . ')' : 'NONE') . "\n";

echo "\n--- All CashBoxes for User 6 ---\n";
foreach ($u->cashBoxes as $cb) {
    echo "- ID: {$cb->id}, Co: {$cb->company_id}, Bal: {$cb->balance}, Default: {$cb->is_default}\n";
}

echo "\n--- Company User Pivot for User 6 ---\n";
$pus = DB::table('company_user')->where('user_id', 6)->get();
foreach ($pus as $p) {
    echo "- Co: {$p->company_id}, Bal in Co: {$p->balance_in_company}\n";
}
