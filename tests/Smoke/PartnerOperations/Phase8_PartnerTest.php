<?php
require __DIR__ . '/../../../vendor/autoload.php';
$app = require_once __DIR__ . '/../../../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\User;
use App\Models\Company;
use App\Models\CashBox;
use App\Services\PartnerOperationService;
use App\Models\PartnerOperation;
use Illuminate\Support\Facades\Auth;

$user = User::first();
Auth::login($user);
$company = Company::first();
$user->active_company_id = $company->id;
$user->save();

$cashBox = CashBox::where('company_id', $company->id)->first();
$initialBalance = $cashBox->balance;

echo "=== START PARTNER TEST | INITIAL CASH: $initialBalance ===\n";

$service = app(PartnerOperationService::class);

// 1. Capital Increase (Deposit)
$op1 = $service->executeOperation([
    'type' => 'capital_increase',
    'amount' => 5000,
    'cashbox_id' => $cashBox->id,
    'partner_id' => $user->id,
    'notes' => 'Test increase',
]);

$cashBox->refresh();
if (bccomp($cashBox->balance, $initialBalance + 5000, 2) === 0) {
    echo "[PASS] Capital Increase -> Cashbox +5000\n";
} else {
    echo "[FAIL] Cashbox balance mismatch. Expected: " . ($initialBalance + 5000) . " Got: " . $cashBox->balance . "\n";
}

// 2. Reverse
$service->reverseOperation($op1, $user->id);
$cashBox->refresh();
if (bccomp($cashBox->balance, $initialBalance, 2) === 0) {
    echo "[PASS] Capital Reversal -> Cashbox restored\n";
} else {
    echo "[FAIL] Cashbox not restored after reversal.\n";
}

// 3. Profit Distribution (Withdraw)
$op2 = $service->executeOperation([
    'type' => 'profit_distribution',
    'amount' => 1000,
    'cashbox_id' => $cashBox->id,
    'partner_id' => $user->id,
    'notes' => 'Test profit',
]);
$cashBox->refresh();
if (bccomp($cashBox->balance, $initialBalance - 1000, 2) === 0) {
    echo "[PASS] Profit Distribution -> Cashbox -1000\n";
} else {
    echo "[FAIL] Cashbox balance mismatch.\n";
}

// 4. Reverse
$service->reverseOperation($op2, $user->id);
$cashBox->refresh();
if (bccomp($cashBox->balance, $initialBalance, 2) === 0) {
    echo "[PASS] Profit Reversal -> Cashbox restored\n";
} else {
    echo "[FAIL] Cashbox not restored after reversal.\n";
}

echo "=== ALL PASSED ===\n";
