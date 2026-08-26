<?php

require __DIR__ . '/../../../vendor/autoload.php';
$app = require_once __DIR__ . '/../../../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Modules\Accounting\Models\CashBox;
use App\Models\Revenue;
use App\Models\User;
use App\Models\FinancialLedger;
use App\Services\RevenueService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

try {
    $admin = User::first();
    Auth::login($admin);
    $companyId = $admin->active_company_id;

    $cashbox = CashBox::where('company_id', $companyId)->first();
    if (!$cashbox) {
        die("No cashbox found.\n");
    }

    $initialCash = $cashbox->balance;
    echo "=== REVENUE TEST | INITIAL CASH: {$initialCash} ===\n";

    $service = app(RevenueService::class);

    DB::beginTransaction();

    // 1. Create Revenue
    $revenue = $service->createRevenue([
        'source_type' => 'manual_revenue',
        'source_id' => 0,
        'user_id' => $admin->id,
        'created_by' => $admin->id,
        'wallet_id' => $cashbox->id,
        'company_id' => $companyId,
        'amount' => 500,
        'note' => 'Test Revenue',
    ]);

    $cashbox->refresh();
    if (round((float)$cashbox->balance, 2) !== round($initialCash + 500, 2)) {
        throw new Exception("Cashbox not updated correctly. Expected: " . ($initialCash + 500) . " Got: {$cashbox->balance}");
    }

    $ledgers = FinancialLedger::where('financial_operation_id', $revenue->financial_operation_id)->get();
    if ($ledgers->count() !== 2) {
        throw new Exception("Expected 2 ledger entries, got " . $ledgers->count());
    }

    echo "[PASS] Revenue Creation -> Cashbox +500, Ledgers created.\n";

    // 2. Reverse Revenue
    $service->reverseRevenue($revenue, $admin->id);

    $cashbox->refresh();
    if (round((float)$cashbox->balance, 2) !== round((float)$initialCash, 2)) {
        throw new Exception("Cashbox not restored after reversal. Expected: {$initialCash} Got: {$cashbox->balance}");
    }

    if ($revenue->status !== 'reversed') {
        throw new Exception("Revenue status not updated to reversed.");
    }

    echo "[PASS] Revenue Reversal -> Cashbox restored, Status updated.\n";

    DB::rollBack();
    echo "=== ALL PASSED ===\n";

} catch (Throwable $e) {
    DB::rollBack();
    echo "[FAIL] " . $e->getMessage() . "\n";
    echo $e->getTraceAsString();
}
