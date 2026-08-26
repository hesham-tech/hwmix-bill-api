<?php

require __DIR__ . '/../../vendor/autoload.php';
$app = require_once __DIR__ . '/../../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
$kernel->handle(Illuminate\Http\Request::capture());

use App\Models\User;
use Modules\Accounting\Models\CashBox;
use App\Models\Expense;
use App\Models\InstallmentPlan;
use App\Models\Installment;
use App\Services\ExpenseService;
use App\Services\InstallmentPaymentService;
use App\Http\Controllers\PaymentController;

function assertCondition($condition, $message) {
    if ($condition) {
        echo "[PASS] $message\n";
    } else {
        echo "[FAIL] $message\n";
        exit(1);
    }
}

try {
    $user = User::first();
    auth()->login($user);
    $companyId = $user->active_company_id;

    $cashBox = CashBox::where('company_id', $companyId)->first();
    if (!$cashBox) $cashBox = CashBox::create(['name' => 'Smoke Box', 'company_id' => $companyId, 'currency_id' => 1]);
    
    $initialBalance = $cashBox->balance;
    echo "=== SMOKE TEST START | Initial Balance: $initialBalance ===\n\n";

    // --- 1. EXPENSE TEST ---
    echo "--- EXPENSE SMOKE TEST ---\n";
    $expenseService = app(ExpenseService::class);
    $expense = $expenseService->createExpense([
        'company_id' => $companyId,
        'expense_category_id' => \App\Models\ExpenseCategory::firstOrCreate(['name' => 'Test', 'company_id' => $companyId])->id,
        'cash_box_id' => $cashBox->id,
        'amount' => 100,
        'expense_date' => now(),
    ], $user->id);
    
    $cashBox->refresh();
    assertCondition(bccomp($cashBox->balance, bcsub($initialBalance, 100, 2), 2) === 0, "Expense Created -> CashBox reduced by 100.");
    
    $expenseService->reverseExpense($expense, $user->id);
    $cashBox->refresh();
    assertCondition(bccomp($cashBox->balance, $initialBalance, 2) === 0, "Expense Reversed -> CashBox restored.");


    // --- 2. INSTALLMENTS TEST ---
    echo "\n--- INSTALLMENTS SMOKE TEST ---\n";
    $planId = \Illuminate\Support\Facades\DB::table('installment_plans')->insertGetId([
        'company_id' => $companyId,
        'invoice_id' => 1,
        'user_id' => $user->id,
        'created_by' => $user->id,
        'total_amount' => 200,
        'net_amount' => 200,
        'remaining_amount' => 200,
        'status' => 'pending',
        'number_of_installments' => 1,
        'frequency' => 'monthly',
        'installment_amount' => 200,
        'start_date' => now(),
        'end_date' => now()->addMonths(1),
    ]);
    
    $plan = InstallmentPlan::find($planId);
    $inst1Id = \Illuminate\Support\Facades\DB::table('installments')->insertGetId([
        'installment_plan_id' => $plan->id,
        'company_id' => $companyId,
        'user_id' => $user->id,
        'created_by' => $user->id,
        'amount' => 200,
        'remaining' => 200,
        'due_date' => now()->addDays(30),
        'status' => 'pending',
    ]);
    $inst1 = Installment::find($inst1Id);

    $instService = app(InstallmentPaymentService::class);
    $payResult = $instService->payInstallments([$inst1->id], 200, [
        'installment_plan_id' => $plan->id,
        'cash_box_id' => $cashBox->id,
        'paid_at' => now(),
    ]);
    $instPayment = $payResult['installmentPayment'];
    
    $cashBox->refresh();
    assertCondition(bccomp($cashBox->balance, bcadd($initialBalance, 200, 2), 2) === 0, "Installment Paid -> CashBox increased by 200.");
    
    $inst1->refresh();
    assertCondition($inst1->status === 'paid' && $inst1->remaining == 0, "Installment Paid -> Status updated to 'paid'.");

    // --- 3. GENERIC PAYMENT GUARD TEST ---
    echo "\n--- GENERIC PAYMENT GUARD TEST ---\n";
    $genericPayment = \App\Models\Payment::where('financial_operation_id', $instPayment->financial_operation_id)->first();
    $paymentController = app(PaymentController::class);
    $response = $paymentController->destroy($genericPayment->id);
    assertCondition($response->getStatusCode() === 409, "Generic Payment Deletion Blocked -> Returned 409.");

    // --- 4. INSTALLMENT REVERSAL TEST ---
    echo "\n--- INSTALLMENT REVERSAL TEST ---\n";
    $instService->reversePayment($instPayment, $user->id);
    $cashBox->refresh();
    assertCondition(bccomp($cashBox->balance, $initialBalance, 2) === 0, "Installment Reversed -> CashBox restored.");
    
    $inst1->refresh();
    assertCondition($inst1->status === 'pending' && $inst1->remaining == 200, "Installment Reversed -> Installment restored to pending.");

    echo "\n=== ALL SMOKE TESTS PASSED PERFECTLY! ===\n";

} catch (\Throwable $e) {
    echo "\n[ERROR] " . $e->getMessage() . "\n" . $e->getTraceAsString();
}
