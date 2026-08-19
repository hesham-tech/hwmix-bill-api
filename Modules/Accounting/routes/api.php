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
use Modules\Accounting\Http\Controllers\CustodyController;

Route::middleware(['auth:sanctum'])->prefix('v1')->group(function () {
    
    // Ø§Ù„ØµÙ†Ø§Ø¯ÙŠÙ‚
    Route::get('cash-boxes/summary', [CashBoxController::class, 'summary']);
    Route::get('cash-boxes/{cashBox}/users', [CashBoxController::class, 'getUsers']);
    Route::post('cash-boxes/{cashBox}/users', [CashBoxController::class, 'syncUsers']);
    Route::post('cash-boxes/transfer', [CashBoxController::class, 'transferFunds']);
    Route::apiResource('cash-boxes', CashBoxController::class);
    Route::apiResource('cash-box-types', CashBoxTypeController::class);
    Route::patch('cash-box-types/{id}/toggle', [CashBoxTypeController::class, 'toggle']);
    
    // Ù…Ø·Ø§Ø¨Ù‚Ø© ÙˆØªØ³ÙˆÙŠØ© Ø§Ù„Ø®Ø²Ù†
    Route::post('cash-reconciliations/{id}/approve', [CashReconciliationController::class, 'approve']);
    Route::apiResource('cash-reconciliations', CashReconciliationController::class)->only(['index', 'store']);

    // Ù…Ø¹Ø§Ù…Ù„Ø§Øª Ø§Ù„Ù…Ù„Ø§Ùƒ ÙˆØ§Ù„Ø´Ø±ÙƒØ§Ø¡
    Route::post('owner-fund-transactions/{ownerFundTransaction}/reverse', [OwnerFundTransactionController::class, 'reverse'])->middleware('idempotency');
    Route::apiResource('owner-fund-transactions', OwnerFundTransactionController::class)->only(['index', 'store']);
    Route::post('owner-fund-transactions', [OwnerFundTransactionController::class, 'store'])->middleware('idempotency'); // Override store to add idempotency
    
    // Ø§Ù„Ù…Ø¹Ø§Ù…Ù„Ø§Øª
    Route::post('transactions/transfer', [TransactionController::class, 'transfer']);
    Route::post('transactions/deposit', [TransactionController::class, 'deposit']);
    Route::post('transactions/withdraw', [TransactionController::class, 'withdraw']);
    Route::post('transactions/{transaction}/reverse', [TransactionController::class, 'reverseTransaction']);
    Route::get('transactions', [TransactionController::class, 'transactions']);
    Route::get('cash-boxes/{cashBox}/transactions', [TransactionController::class, 'userTransactions']);
    
    // Ø§Ù„Ù…ØµØ§Ø±ÙŠÙ
    Route::get('expenses/summary', [ExpenseController::class, 'getSummary']);
    Route::apiResource('expenses', ExpenseController::class);
    Route::apiResource('expense-categories', ExpenseCategoryController::class);
    
    // Custody
    Route::apiResource('custodies', CustodyController::class);
    Route::post('custodies/{custody}/refund', [CustodyController::class, 'refund']);
    Route::post('custodies/{custody}/reverse', [CustodyController::class, 'reverse']);
    
    // Ø§Ù„Ø¥ÙŠØ±Ø§Ø¯Ø§Øª
    Route::apiResource('revenues', RevenueController::class);

    // Ø³Ø¬Ù„ Ø§Ù„Ø£Ø³ØªØ§Ø° Ø§Ù„Ù…Ø§Ù„ÙŠ
    Route::get('financial-ledger', [FinancialLedgerController::class, 'index']);
    Route::post('financial-ledger/export', [FinancialLedgerController::class, 'export']);
});
