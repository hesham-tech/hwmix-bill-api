<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\LogController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\AnalyticsController;

use App\Http\Controllers\StockController;
use App\Http\Controllers\ProfitController;
use App\Http\Controllers\ArtisanController;
use App\Http\Controllers\CashBoxController;
use App\Http\Controllers\CompanyController;
use App\Http\Controllers\InvoiceController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\RevenueController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\ImageController;
use App\Http\Controllers\ProductImportController;
use App\Http\Controllers\GlobalSearchController;

use App\Http\Controllers\AttributeController;

use App\Http\Controllers\PermissionController;
use App\Http\Controllers\CashBoxTypeController;
use App\Http\Controllers\InstallmentController;
use App\Http\Controllers\InvoiceItemController;
use App\Http\Controllers\InvoiceTypeController;
use App\Http\Controllers\TransactionController;
use App\Http\Controllers\PaymentMethodController;
use App\Http\Controllers\AttributeValueController;
use App\Http\Controllers\ProductVariantController;
use App\Http\Controllers\InstallmentPlanController;
use App\Http\Controllers\InstallmentPaymentController;
use App\Http\Controllers\InstallmentPaymentDetailController;
use App\Http\Controllers\PlanController;
use App\Http\Controllers\Api\DevToolController;
use App\Http\Controllers\Api\TaskController;
use App\Http\Controllers\Api\TaskGroupController;
use App\Http\Controllers\Api\BackupController;
use App\Http\Controllers\Api\ErrorReportController;
use App\Http\Controllers\ServiceController;
use App\Http\Controllers\SubscriptionController;
use App\Http\Controllers\BootstrapController;
use App\Http\Controllers\UserTablePreferenceController;



Route::get('/fix-missing-default-cashboxes', [\App\Http\Controllers\MaintenanceController::class, 'fixMissingCashBoxes'])->name('emergency.fix.cashboxes');


Route::middleware('throttle:auth')->group(function () {
    Route::post('register/customer', [\App\Http\Controllers\Api\Auth\MarketplaceRegisterController::class, 'register']);
    Route::post('register/company', [\App\Http\Controllers\Api\Auth\TenantProvisioningController::class, 'register']);
    Route::post('login', [AuthController::class, 'login']);
});
Route::get('public/plans', [PlanController::class, 'publicPlans']);
Route::get('public/company', [CompanyController::class, 'publicCompany']);
Route::post('error-reports', [ErrorReportController::class, 'store']);
Route::get('media/view/{path}', [ImageController::class, 'serve'])->where('path', '.*')->name('media.serve');

Route::middleware(['auth:sanctum', 'scope_company', 'branch_context', 'throttle:api'])->group(function () {
    Route::post('logout', [AuthController::class, 'logout']);
    Route::get('/auth/check', [AuthController::class, 'checkLogin']);

    // Session Management
    Route::get('/auth/sessions', [AuthController::class, 'listTokens']);
    Route::delete('/auth/sessions-others', [AuthController::class, 'revokeAllOtherTokens']);
    Route::delete('/auth/sessions/{id}', [AuthController::class, 'revokeToken']);
    // Health check
    Route::get('/artisan/health', [ArtisanController::class, 'health']);

    // Error Reporting Management
    Route::controller(ErrorReportController::class)->group(function () {
        Route::get('error-reports', 'index');
        Route::patch('error-reports/{errorReport}', 'update');
    });

    // ================== Reports Routes ==================
    Route::prefix('reports')->group(function () {
        // Sales Reports
        Route::get('/sales', [\App\Http\Controllers\Reports\SalesReportController::class, 'index']);
        Route::get('/sales/top-products', [\App\Http\Controllers\Reports\SalesReportController::class, 'topProducts']);
        Route::get('/sales/top-customers', [\App\Http\Controllers\Reports\SalesReportController::class, 'topCustomers']);
        Route::get('/sales/trend', [\App\Http\Controllers\Reports\SalesReportController::class, 'trend']);

        // Profit & Loss Reports
        Route::get('/profit-loss', [\App\Http\Controllers\Reports\ProfitLossReportController::class, 'index']);
        Route::get('/profit-loss-summary', [\App\Http\Controllers\Reports\ProfitLossReportController::class, 'index']);
        Route::get('/profit-loss/monthly-comparison', [\App\Http\Controllers\Reports\ProfitLossReportController::class, 'monthlyComparison']);

        // Stock Reports
        Route::get('/stock', [\App\Http\Controllers\Reports\StockReportController::class, 'index']);
        Route::get('/stock/valuation', [\App\Http\Controllers\Reports\StockReportController::class, 'valuation']);
        Route::get('/stock/low-stock', [\App\Http\Controllers\Reports\StockReportController::class, 'lowStock']);
        Route::get('/stock/inactive', [\App\Http\Controllers\Reports\StockReportController::class, 'inactiveStock']);

        // Cash Flow Reports
        Route::get('/cash-flow', [\App\Http\Controllers\Reports\CashFlowReportController::class, 'index']);
        Route::get('/cash-flow/by-cash-box', [\App\Http\Controllers\Reports\CashFlowReportController::class, 'byCashBox']);
        Route::get('/cash-flow/summary', [\App\Http\Controllers\Reports\CashFlowReportController::class, 'summary']);
        Route::get('/cash-flow/trend', [\App\Http\Controllers\Reports\CashFlowReportController::class, 'trend']);

        // Tax Reports
        Route::get('/tax', [\App\Http\Controllers\Reports\TaxReportController::class, 'index']);
        Route::get('/tax/collected', [\App\Http\Controllers\Reports\TaxReportController::class, 'collected']);
        Route::get('/tax/paid', [\App\Http\Controllers\Reports\TaxReportController::class, 'paid']);
        Route::get('/tax/net', [\App\Http\Controllers\Reports\TaxReportController::class, 'netTax']);

        // Customer/Supplier Reports
        Route::get('/customers/top', [\App\Http\Controllers\Reports\CustomerSupplierReportController::class, 'topCustomers']);
        Route::get('/customers/debts', [\App\Http\Controllers\Reports\CustomerSupplierReportController::class, 'customerDebts']);
        Route::get('/suppliers/debts', [\App\Http\Controllers\Reports\CustomerSupplierReportController::class, 'supplierDebts']);
        Route::get('/customers/performance', [\App\Http\Controllers\Reports\CustomerSupplierReportController::class, 'performance']);
    });

    // ================== Activity Logs (Audit Trail) ==================
    Route::prefix('activity-logs')->group(function () {
        Route::get('/', [\App\Http\Controllers\ActivityController::class, 'index']);
        Route::get('/{id}', [\App\Http\Controllers\ActivityController::class, 'show']);
        Route::get('/subject/logs', [\App\Http\Controllers\ActivityController::class, 'forSubject']);
        Route::get('/user/{userId}', [\App\Http\Controllers\ActivityController::class, 'userActivity']);
        Route::get('/invoice/{invoiceId}', [\App\Http\Controllers\ActivityController::class, 'invoiceActivities']);
        Route::post('/export', [\App\Http\Controllers\ActivityController::class, 'export']);
    });

    // ================== Dashboard & Analytics ==================
    Route::get('/dashboard/summary', [\App\Http\Controllers\DashboardController::class, 'index']);
    Route::controller(AnalyticsController::class)->prefix('analytics')->group(function () {
        Route::get('dashboard', 'dashboard');
        Route::get('top-products', 'topProducts');
        Route::get('user-history/{userId}', 'userHistory');
    });
    // Auth Controller
    Route::get('me', [AuthController::class, 'me']);
    Route::get('bootstrap', [BootstrapController::class, 'index']);
    Route::prefix('ui-preferences')->group(function () {
        Route::get('/', [UserTablePreferenceController::class, 'index']);
        Route::post('/', [UserTablePreferenceController::class, 'store']);
        Route::delete('/{tableKey}', [UserTablePreferenceController::class, 'reset']);
        Route::post('/reset-all', [UserTablePreferenceController::class, 'resetAll']);
    });
    // User Controller
    Route::controller(UserController::class)
        ->group(function () {
            Route::get('users', 'index');
            Route::get('users/staff', 'staff');
            Route::get('users/customers', 'customers');
            Route::get('users/lookup', 'lookup');
            Route::get('users/stats', 'stats');
            Route::get('users/search', 'usersSearch');
            Route::get('users/search-advanced', 'indexWithSearch');
            Route::post('users', 'store')->middleware('saas.limit:users');
            Route::get('users/{user}', 'show');
            Route::put('users/{user}', 'update');
            Route::put('change-company/{userId}', 'changeCompany');
            Route::put('users/{user}/cashbox/{cashBoxId}/set-default', 'setDefaultCashBox');
            Route::post('users/delete', 'destroy');
            Route::delete('users/{user}', 'destroy');
        });

    // company Controller
    Route::controller(CompanyController::class)
        ->group(function () {
            Route::get('companies', 'index');
            Route::post('companies', 'store');
            Route::get('companies/{company}', 'show');
            Route::put('companies/{company}', 'update');
            Route::post('companies/delete', 'destroy');
        });

    // Images Controller
    Route::controller(ImageController::class)
        ->group(function () {
            Route::get('images', 'index');
            Route::post('images', 'store');
            Route::put('images/{image}', 'update');
            Route::post('images/{image}/set-primary', 'setPrimary');
            Route::post('images/delete', 'destroy');
        });

    // Backup Controller
    Route::controller(BackupController::class)->group(function () {
        Route::get('backups', 'index');
        Route::post('backups/run', 'run');
        Route::get('backups/{id}/download', 'download');
        Route::delete('backups/{id}', 'destroy');
        Route::get('backups/settings', 'getSettings');
        Route::put('backups/settings', 'updateSettings');
        Route::post('backups/{id}/restore', 'restore');
    });


    // Transaction Controller (Moved to Accounting Module)
    // Services & Invoices (Moved to Sales Module)
    
    // Role Controller
    Route::controller(RoleController::class)
        ->group(function () {
            Route::get('roles', 'index');
            Route::post('roles', 'store');
            Route::get('roles/{role}', 'show');
            Route::put('roles/{role}', 'update');
            Route::delete('roles/{role}', 'destroy'); // Single delete
            Route::post('roles/batch-delete', 'destroy'); // Batch delete
            Route::post('roles/assign', 'assignRole');
        });

    // Logs Controller
    Route::controller(LogController::class)
        ->group(function () {
            Route::get('logs', 'index');
            Route::post('logs/{log}/undo', 'undo');
        });

    // InstallmentPlan Controller
    Route::controller(InstallmentPlanController::class)->group(function () {
        Route::get('installment-plans', 'index');
        Route::post('installment-plans', 'store');
        Route::get('installment-plans/{installmentPlan}', 'show');
        Route::put('installment-plans/{installmentPlan}', 'update');
        Route::delete('installment-plans/{installmentPlan}', 'destroy');
    });

    // Installment Controller
    Route::controller(InstallmentController::class)->group(function () {
        Route::get('installments', 'index');
        Route::post('installments', 'store');
        Route::get('installments/{installment}', 'show');
        Route::put('installments/{installment}', 'update');
        Route::delete('installments/{installment}', 'destroy');
    });
    // Payment Controller
    Route::controller(PaymentController::class)->group(function () {
        Route::get('payments', 'index');
        Route::post('payments', 'store');
        Route::get('payments/{payment}', 'show');
        Route::put('payments/{payment}', 'update');
        Route::delete('payments/{payment}', 'destroy');
    });
    // PaymentMethod Controller
    Route::controller(PaymentMethodController::class)
        ->group(function () {
            Route::get('payment-methods', 'index');
            Route::post('payment-methods', 'store');

            Route::get('payment-methods/{paymentMethod}', 'show');
            Route::put('payment-methods/{paymentMethod}', 'update');
            Route::patch('payment-methods/{id}/toggle', 'toggle');
            Route::delete('payment-methods/{paymentMethod}', 'destroy');
        });
    Route::controller(InstallmentPaymentController::class)
        ->group(function () {
            Route::get('installment-payments', 'index');
            Route::post('installment-payments', 'store');
            Route::post('installment-payments/pay', 'payInstallments');
            Route::get('installment-payments/{installmentPayment}', 'show');
            Route::put('installment-payments/{installmentPayment}', 'update');
            Route::delete('installment-payments/{installmentPayment}', 'destroy');
        });
    // Revenue Controller (Moved to Accounting Module)
    // Profit Controller
    Route::controller(ProfitController::class)
        ->group(function () {
            Route::get('profits', 'index');
            Route::post('profits', 'store');
            Route::get('profits/{profit}', 'show');
            Route::put('profits/{profit}', 'update');
            Route::delete('profits/{profit}', 'destroy');
        });
    // InstallmentPaymentDetail Controller
    Route::controller(InstallmentPaymentDetailController::class)
        ->group(function () {
            Route::get('installment-payment-details', 'index');
            Route::post('installment-payment-detail', 'store');
            Route::get('installment-payment-detail/{installmentPaymentDetail}', 'show');
            Route::put('installment-payment-detail/{installmentPaymentDetail}', 'update');
            Route::delete('installment-payment-detail/delete/{installmentPaymentDetail}', 'destroy');
        });
    // Plan Controller
    Route::apiResource('plans', PlanController::class);

    // SaaS Subscription Management
    Route::get('saas/my-subscription', [\App\Http\Controllers\SaaS\SaaSSubscriptionController::class, 'mySubscription']);
    Route::patch('saas/my-subscription/toggle-auto-renew', [\App\Http\Controllers\SaaS\SaaSSubscriptionController::class, 'toggleAutoRenew']);
    Route::post('saas/my-subscription/upgrade', [\App\Http\Controllers\SaaS\SaaSSubscriptionController::class, 'upgrade']);
    Route::get('saas/companies-subscriptions', [\App\Http\Controllers\SaaS\SaaSSubscriptionController::class, 'companiesSubscriptions']);
    Route::post('saas/companies-subscriptions/change-plan', [\App\Http\Controllers\SaaS\SaaSSubscriptionController::class, 'changeCompanyPlan']);

    Route::get('/permissions', [PermissionController::class, 'index']);

    // ================== Financials (Expenses & Ledger) (Moved to Accounting Module) ==================

    // Summary Reports (High Performance)
    Route::get('reports/profit-loss-summary', [\App\Http\Controllers\Reports\ProfitLossReportController::class, 'profitLossSummary']);

    // ================== Task Management ==================
    Route::apiResource('tasks', TaskController::class);
    Route::post('tasks/{task}/comments', [TaskController::class, 'addComment']);
    Route::post('tasks/{task}/attachments', [TaskController::class, 'uploadAttachment']);
    Route::apiResource('task-groups', TaskGroupController::class);

    // ================== Dev Tools ==================
    Route::prefix('dev')->group(function () {
        Route::get('/testing-checklist', [DevToolController::class, 'getTestingChecklist']);
        Route::post('/testing-checklist', [DevToolController::class, 'saveTestingChecklist']);
    });

    // Global Search
    Route::get('global-search', 'App\Http\Controllers\GlobalSearchController@search');

    // Artisan commands routes (Secured)
    Route::controller(ArtisanController::class)->prefix('php')->group(function () {
        Route::get('runComposerDump', 'runComposerDump'); 
        Route::get('generateBackup', 'generateBackup'); 
        Route::get('migrate', 'migrate'); 
        Route::get('migrateAndSeed', 'migrateAndSeed'); 
        Route::get('applyBackup', 'applyBackup');     
        Route::get('PermissionsSeeder', 'PermissionsSeeder'); 
        Route::get('DatabaseSeeder', 'DatabaseSeeder'); 
        Route::get('seedRolesAndPermissions', 'seedRolesAndPermissions'); 
        Route::get('clear', 'clearAllCache'); 
    });
});
