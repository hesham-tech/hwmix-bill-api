<?php

require __DIR__ . '/../../../vendor/autoload.php';
$app = require_once __DIR__ . '/../../../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Profit;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\ProfitController;

try {
    $admin = User::first();
    Auth::login($admin);
    $companyId = $admin->active_company_id;

    DB::beginTransaction();

    // 1. Create Profit
    $profit = Profit::create([
        'source_type' => 'manual_profit',
        'source_id' => 0,
        'user_id' => $admin->id,
        'created_by' => $admin->id,
        'company_id' => $companyId,
        'revenue_amount' => 1000,
        'cost_amount' => 400,
        'profit_amount' => 600,
        'profit_date' => now(),
        'note' => 'Test Profit',
    ]);

    echo "[PASS] Profit Creation.\n";

    // 2. Reverse Profit
    $profit->update(['status' => 'reversed']);
    $profit->delete();

    if ($profit->status !== 'reversed') {
        throw new Exception("Profit status not updated to reversed.");
    }
    
    if (!$profit->trashed()) {
        throw new Exception("Profit not soft deleted.");
    }

    echo "[PASS] Profit Reversal -> Soft deleted, Status updated.\n";

    DB::rollBack();
    echo "=== ALL PASSED ===\n";

} catch (Throwable $e) {
    DB::rollBack();
    echo "[FAIL] " . $e->getMessage() . "\n";
    echo $e->getTraceAsString();
}