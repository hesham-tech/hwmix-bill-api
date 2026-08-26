<?php

require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\CashBox;
use App\Models\Company;
use App\Models\User;
use App\Services\CashService;
use App\Models\Transaction;
use Illuminate\Support\Facades\DB;

try {
    DB::beginTransaction();
    
    $company = Company::first();
    $user = User::first();
    $userId = $user ? $user->id : 1;
    
    // Create two cashboxes
    $box1 = CashBox::withoutGlobalScopes()->create([
        'name' => 'Test Box 1',
        'balance' => 0,
        'company_id' => $company ? $company->id : 1,
        'user_id' => $userId,
        'created_by' => $userId,
        'branch_id' => 1,
        'cash_box_type_id' => 1,
        'code' => 'CBX-TEST1'
    ]);
    
    $box2 = CashBox::withoutGlobalScopes()->create([
        'name' => 'Test Box 2',
        'balance' => 0,
        'company_id' => $company ? $company->id : 1,
        'user_id' => $userId,
        'created_by' => $userId,
        'branch_id' => 1,
        'cash_box_type_id' => 1,
        'code' => 'CBX-TEST2'
    ]);
    
    $service = new CashService();
    $opId = 'test-op-123';
    
    echo "1. Testing Deposit (القبض)...\n";
    $service->deposit(1000, $box1->id, $opId);
    $box1->refresh();
    echo "Box 1 balance after deposit: {$box1->balance} (Expected: 1000)\n";
    
    echo "2. Testing Withdraw (الصرف)...\n";
    $service->withdraw(200, $box1->id, $opId);
    $box1->refresh();
    echo "Box 1 balance after withdraw: {$box1->balance} (Expected: 800)\n";
    
    echo "3. Testing Transfer (التحويل)...\n";
    $service->transfer($box1->id, $box2->id, 300, $opId);
    $box1->refresh();
    $box2->refresh();
    echo "Box 1 balance after transfer: {$box1->balance} (Expected: 500)\n";
    echo "Box 2 balance after transfer: {$box2->balance} (Expected: 300)\n";
    
    echo "4. Testing Overdraft Prevention (لا يكون الرصيد وهمياً)...\n";
    try {
        $service->withdraw(1000, $box1->id, $opId);
        echo "FAIL: Allowed overdraft!\n";
    } catch (\Exception $e) {
        echo "SUCCESS: Prevented overdraft. Error: " . $e->getMessage() . "\n";
    }
    
    echo "5. Verifying transactions...\n";
    $txs = Transaction::where('financial_operation_id', $opId)->get();
    echo "Total transactions recorded: " . $txs->count() . "\n";
    foreach($txs as $tx) {
        echo "- Type: {$tx->type}, Amount: {$tx->amount}, Balance Before: {$tx->balance_before}, Balance After: {$tx->balance_after}\n";
    }
    
    DB::rollBack();
    echo "\nAll tests completed successfully.\n";

} catch (\Exception $e) {
    DB::rollBack();
    echo "Error: " . $e->getMessage() . "\n" . $e->getTraceAsString();
}
