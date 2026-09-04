<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

try {
    $line = Modules\HwnixCash\Models\HwnixCashLine::first();
    $account = $line->financialAccounts()->first();
    $user = App\Models\User::first();
    
    Modules\HwnixCash\Models\HwnixCashWalletTransaction::create([
        'company_id' => 1,
        'created_by' => $user->id,
        'financial_account_id' => $account->id,
        'line_id' => $line->id,
        'operation_type' => Modules\HwnixCash\Domain\Enums\WalletOperationType::RECONCILIATION->value,
        'provider' => Modules\HwnixCash\Domain\Enums\WalletProvider::VODAFONE_CASH->value,
        'status' => Modules\HwnixCash\Domain\Enums\WalletTransactionStatus::SUCCESS->value,
        'source' => Modules\HwnixCash\Domain\Enums\WalletTransactionSource::MANUAL->value,
        'amount' => 10,
        'fee' => 0.00,
        'balance_after' => 100,
        'currency' => 'EGP',
        'operation_number' => 'REC-APP-' . date('YmdHis') . '-' . $account->id,
        'operation_at' => now(),
        'raw_sms' => 'tsweya',
        'metadata' => ['test' => true],
    ]);
    echo "Success!";
} catch (\Exception $e) {
    echo "Error: " . $e->getMessage();
}
