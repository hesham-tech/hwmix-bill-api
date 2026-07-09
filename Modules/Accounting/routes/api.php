<?php

use Illuminate\Support\Facades\Route;
use Modules\Accounting\Http\Controllers\CashBoxController;
use Modules\Accounting\Http\Controllers\TransactionController;
use Modules\Accounting\Http\Controllers\ExpenseController;
use Modules\Accounting\Http\Controllers\ExpenseCategoryController;
use Modules\Accounting\Http\Controllers\RevenueController;
use Modules\Accounting\Http\Controllers\CashBoxTypeController;
use Modules\Accounting\Http\Controllers\FinancialLedgerController;
use Modules\Accounting\Http\Controllers\OwnerFundTransactionController;
use Modules\Accounting\Http\Controllers\CashReconciliationController;

Route::middleware(['auth:sanctum'])->prefix('v1')->group(function () {
    
    // الصناديق
    Route::get('cash-boxes/summary', [CashBoxController::class, 'summary']);
    Route::get('cash-boxes/{cashBox}/users', [CashBoxController::class, 'getUsers']);
    Route::post('cash-boxes/{cashBox}/users', [CashBoxController::class, 'syncUsers']);
    Route::post('cash-boxes/transfer', [CashBoxController::class, 'transferFunds']);
    Route::apiResource('cash-boxes', CashBoxController::class);
    Route::apiResource('cash-box-types', CashBoxTypeController::class);
    Route::patch('cash-box-types/{id}/toggle', [CashBoxTypeController::class, 'toggle']);
    
    // مطابقة وتسوية الخزن
    Route::post('cash-reconciliations/{id}/approve', [CashReconciliationController::class, 'approve']);
    Route::apiResource('cash-reconciliations', CashReconciliationController::class)->only(['index', 'store']);

    // معاملات الملاك والشركاء
    Route::apiResource('owner-fund-transactions', OwnerFundTransactionController::class)->only(['index', 'store']);
    
    // المعاملات
    Route::post('transactions/transfer', [TransactionController::class, 'transfer']);
    Route::post('transactions/deposit', [TransactionController::class, 'deposit']);
    Route::post('transactions/withdraw', [TransactionController::class, 'withdraw']);
    Route::post('transactions/{transaction}/reverse', [TransactionController::class, 'reverseTransaction']);
    Route::get('transactions', [TransactionController::class, 'transactions']);
    Route::get('cash-boxes/{cashBox}/transactions', [TransactionController::class, 'userTransactions']);
    
    // المصاريف
    Route::get('expenses/summary', [ExpenseController::class, 'getSummary']);
    Route::apiResource('expenses', ExpenseController::class);
    Route::apiResource('expense-categories', ExpenseCategoryController::class);
    
    // الإيرادات
    Route::apiResource('revenues', RevenueController::class);

    // سجل الأستاذ المالي
    Route::get('financial-ledger', [FinancialLedgerController::class, 'index']);
    Route::post('financial-ledger/export', [FinancialLedgerController::class, 'export']);
});
