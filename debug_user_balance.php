<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\User;
use App\Models\Installment;
use Illuminate\Support\Facades\DB;

$user = User::where('nickname', 'like', '%ابو حبيبة%')
    ->orWhere('full_name', 'like', '%ابو حبيبة%')
    ->first();

if (!$user) {
    die("User not found.");
}

echo "User found: " . $user->full_name . " (ID: " . $user->id . ")\n";
echo "Nickname: " . $user->nickname . "\n";
echo "Roles: " . $user->roles->pluck('name')->implode(', ') . "\n";
echo "Permissions Count: " . $user->permissions->count() . "\n";

$cashBoxes = $user->cashBoxes()->get();
echo "\nCashBoxes:\n";
foreach ($cashBoxes as $cb) {
    echo "- ID: {$cb->id}, Name: {$cb->name}, Balance: {$cb->balance}, Company ID: {$cb->company_id}, Default: {$cb->is_default}\n";
}

$unpaidInstallments = DB::table('installments')
    ->where('user_id', $user->id)
    ->whereNull('deleted_at')
    ->whereNotIn('status', ['paid', 'تم الدفع', 'canceled', 'cancelled', 'ملغي'])
    ->get();

echo "\nUnpaid Installments Count: " . $unpaidInstallments->count() . "\n";
$sum = 0;
foreach ($unpaidInstallments as $inst) {
    echo "- ID: {$inst->id}, Amount: {$inst->amount}, Remaining: {$inst->remaining}, Status: {$inst->status}, Company ID: {$inst->company_id}\n";
    $sum += $inst->remaining;
}
echo "Total Remaining in installments: " . $sum . "\n";
