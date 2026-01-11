<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\LogController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\BrandController;
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
use App\Http\Controllers\AttributeController;
use App\Http\Controllers\WarehouseController;
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

Route::get('/fix-missing-default-cashboxes', [\App\Http\Controllers\MaintenanceController::class, 'fixMissingCashBoxes'])->name('emergency.fix.cashboxes');


Route::post('register', [AuthController::class, 'register']);
Route::post('login', [AuthController::class, 'login']);
Route::middleware(['auth:sanctum'])->group(function () {
    Route::post('logout', [AuthController::class, 'logout']);
    Route::get('/auth/check', [AuthController::class, 'checkLogin']);
    // Health check
    Route::get('/artisan/health', [ArtisanController::class, 'health']);

    // ================== Reports Routes ==================
    Route::prefix('reports')->group(function () {
        // Sales Reports
        Route::get('/sales', [\App\Http\Controllers\Reports\SalesReportController::class, 'index']);
        Route::get('/sales/top-products', [\App\Http\Controllers\Reports\SalesReportController::class, 'topProducts']);
        Route::get('/sales/top-customers', [\App\Http\Controllers\Reports\SalesReportController::class, 'topCustomers']);
        Route::get('/sales/trend', [\App\Http\Controllers\Reports\SalesReportController::class, 'trend']);

        // Profit & Loss Reports
        Route::get('/profit-loss', [\App\Http\Controllers\Reports\ProfitLossReportController::class, 'index']);
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

    // ================== Dashboard ==================
    Route::get('/dashboard/summary', [\App\Http\Controllers\DashboardController::class, 'index']);
    // Auth Controller
    Route::get('me', [AuthController::class, 'me']);
    // User Controller
    Route::controller(UserController::class)
        ->group(function () {
            Route::get('users', 'index');
            Route::get('users/lookup', 'lookup');
            Route::get('users/stats', 'stats');
            Route::get('users/search', 'usersSearch');
            Route::get('users/search-advanced', 'indexWithSearch');
            Route::post('users', 'store');
            Route::get('users/{user}', 'show');
            Route::put('users/{user}', 'update');
            Route::put('change-company/{user}', 'changeCompany');
            Route::put('users/{user}/cashbox/{cashBoxId}/set-default', 'setDefaultCashBox');
            Route::post('users/delete', 'destroy');
        });
    // company Controller
    Route::controller(CompanyController::class)
        ->group(function () {
            Route::get('companys', 'index');
            Route::post('company', 'store');
            Route::get('company/{company}', 'show');
            Route::put('company/{company}', 'update');
            Route::post('company/delete', 'destroy');
        });

    // Images Controller
    Route::controller(ImageController::class)
        ->group(function () {
            Route::get('images', 'index');
            Route::post('images', 'store');
            Route::put('images/{Image}', 'update');
            Route::post('images/delete', 'destroy');
        });


    // Transaction Controller
    Route::controller(TransactionController::class)
        ->group(function () {
            Route::post('/transfer', 'transfer');
            Route::post('/deposit', 'deposit');
            Route::post('/withdraw', 'withdraw');
            Route::get('/transactions', 'transactions');
            Route::get('transactions/user/{cashBoxId?}', 'userTransactions');
            Route::post('/transactions/{transaction}/reverse', 'reverseTransaction');
        });
    // Invoice Controller
    Route::controller(InvoiceController::class)
        ->group(function () {
            Route::get('invoices', 'index');
            Route::post('invoices', 'store');
            Route::get('invoices/{invoice}', 'show');
            Route::put('invoices/{invoice}', 'update');
            Route::delete('invoices/{invoice}', 'destroy');
            Route::post('invoices/deletes', 'deleteMultiple');

            // PDF Routes
            Route::get('invoice/{id}/pdf', 'downloadPDF')->name('invoice.download-pdf');
            Route::get('invoice/{id}/pdf-data', 'getInvoiceForPDF')->name('invoice.pdf-data');
            Route::post('invoice/{id}/email-pdf', 'emailPDF')->name('invoice.email-pdf');

            // Excel Export
            Route::post('invoices/export-excel', 'exportExcel')->name('invoices.export-excel');
        });
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
    // cashBoxTypes Controller
    Route::controller(CashBoxTypeController::class)
        ->group(function () {
            Route::get('cash-box-types', 'index');
            Route::post('cash-box-types', 'store');
            Route::get('cash-box-types/{cashBoxType}', 'show');
            Route::put('cash-box-types/{cashBoxType}', 'update');
            Route::patch('cash-box-types/{id}/toggle', 'toggle');
            Route::delete('cash-box-types/{cashBoxType}', 'destroy');
        });
    // CashBox Controller
    Route::controller(CashBoxController::class)
        ->group(function () {
            Route::get('cash-boxes', 'index');
            Route::post('cash-boxes', 'store');
            Route::get('cash-boxes/{cashBox}', 'show');
            Route::put('cash-boxes/{cashBox}', 'update');
            Route::delete('cash-boxes/{cashBox}', 'destroy');
            Route::post('cash-boxes/transfer', 'transferFunds');
        });
    // Logs Controller
    Route::controller(LogController::class)
        ->group(function () {
            Route::get('logs', 'index');
            Route::post('logs/{log}/undo', 'undo');
        });

    // Product Controller
    Route::controller(ProductController::class)
        ->group(function () {
            Route::get('products', 'index');
            Route::post('products', 'store');
            Route::get('products/{product}', 'show');
            Route::put('products/{product}', 'update');
            Route::delete('products/{product}', 'destroy');
        });
    // Attribute Controller
    Route::controller(AttributeController::class)
        ->group(function () {
            Route::get('attributes', 'index');
            Route::post('attributes', 'store');
            Route::get('attributes/{attribute}', 'show');
            Route::put('attributes/{attribute}', 'update');
            Route::delete('attributes/{attribute}', 'destroy');
            Route::patch('attributes/{id}/toggle', 'toggle');
            Route::post('attribute/deletes', 'deleteMultiple');
        });
    // Attribute Value Controller
    Route::controller(AttributeValueController::class)
        ->group(function () {
            Route::get('attribute-values', 'index');
            Route::post('attribute-values', 'store');
            Route::get('attribute-values/{attributeValue}', 'show');
            Route::put('attribute-values/{attributeValue}', 'update');
            Route::delete('attribute-values/{attributeValue}', 'destroy');
            Route::post('attribute-value/deletes', 'deleteMultiple');
        });
    // Product Variant Controller
    Route::controller(ProductVariantController::class)
        ->group(function () {
            Route::get('product-variants', 'index');
            Route::post('product-variants', 'store');
            Route::get('product-variants/{productVariant}', 'show');
            Route::put('product-variants/{productVariant}', 'update');
            Route::delete('product-variants/{productVariant}', 'destroy');
            Route::post('product-variants/delete', 'deleteMultiple');
            Route::get('product-variants/search-by-product', 'searchByProduct');
        });
    // Warehouse Controller
    Route::controller(WarehouseController::class)
        ->group(function () {
            Route::get('warehouses', 'index');
            Route::post('warehouses', 'store');
            Route::get('warehouses/{warehouse}', 'show');
            Route::put('warehouses/{warehouse}', 'update');
            Route::delete('warehouses/{warehouse}', 'destroy');
        });
    // Stock Controller
    Route::controller(StockController::class)
        ->group(function () {
            Route::get('stocks', 'index');
            Route::post('stock', 'store');
            Route::get('stock/{stock}', 'show');
            Route::put('stock/{stock}', 'update');
            Route::delete('stock/{stock}', 'destroy');
        });
    // Category Controller
    Route::controller(CategoryController::class)
        ->group(function () {
            Route::get('categories', 'index');
            Route::post('categories', 'store');
            Route::get('categories/{category}', 'show');
            Route::put('categories/{category}', 'update');
            Route::patch('categories/{id}/toggle', 'toggle');
            Route::get('categories/{id}/breadcrumbs', 'breadcrumbs');
            Route::delete('categories/{category}', 'destroy');
        });
    // Brand Controller
    Route::controller(BrandController::class)
        ->group(function () {
            Route::get('brands', 'index');
            Route::post('brands', 'store');
            Route::get('brands/{brand}', 'show');
            Route::put('brands/{brand}', 'update');
            Route::patch('brands/{id}/toggle', 'toggle');
            Route::delete('brands/{brand}', 'destroy');
        });
    // InvoiceType Controller
    Route::controller(InvoiceTypeController::class)->group(function () {
        Route::get('invoice-types', 'index');
        Route::post('invoice-types', 'store');
        Route::get('invoice-types/{invoiceType}', 'show');
        Route::put('invoice-types/{invoiceType}', 'update');
        Route::delete('invoice-types/{invoiceType}', 'destroy');
    });

    // InvoiceItem Controller
    Route::controller(InvoiceItemController::class)->group(function () {
        Route::get('invoice-items', 'index');
        Route::post('invoice-item', 'store');
        Route::get('invoice-item/{invoiceItem}', 'show');
        Route::put('invoice-item/{invoiceItem}', 'update');
        Route::delete('invoice-item/{invoiceItem}', 'destroy');
    });
    // InstallmentPlan Controller
    Route::controller(InstallmentPlanController::class)->group(function () {
        Route::get('installment-plans', 'index');
        Route::post('installment-plan', 'store');
        Route::get('installment-plan/{installmentPlan}', 'show');
        Route::put('installment-plan/{installmentPlan}', 'update');
        Route::delete('installment-plan/{installmentPlan}', 'destroy');
    });
    // Installment Controller
    Route::controller(InstallmentController::class)->group(function () {
        Route::get('installments', 'index');
        Route::post('installment', 'store');
        Route::get('installment/{installment}', 'show');
        Route::put('installment/{installment}', 'update');
        Route::delete('installment/{installment}', 'destroy');
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
    // InstallmentPlan Controller
    Route::controller(InstallmentPlanController::class)
        ->group(function () {
            Route::get('installment-plans', 'index');
            Route::post('installment-plans', 'store');
            Route::get('installment-plans/{id}', 'show');
            Route::put('installment-plans/{id}', 'update');
            Route::delete('installment-plans/{id}', 'destroy');
        });
    // Revenue Controller
    Route::controller(RevenueController::class)
        ->group(function () {
            Route::get('revenues', 'index');
            Route::post('revenues', 'store');
            Route::get('revenues/{revenue}', 'show');
            Route::put('revenues/{revenue}', 'update');
            Route::delete('revenues/{revenue}', 'destroy');
        });
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
    // InvoiceItem Controller
    Route::controller(InvoiceItemController::class)
        ->group(function () {
            Route::get('invoice-items', 'index');
            Route::post('invoice-items', 'store');
            Route::get('invoice-items/{id}', 'show');
            Route::put('invoice-items/{id}', 'update');
            Route::delete('invoice-items/{id}', 'destroy');
        });
    // InvoiceType Controller
    Route::controller(InvoiceTypeController::class)
        ->group(function () {
            Route::get('invoice-types', 'index');
            Route::post('invoice-type', 'store');
            Route::get('invoice-type/{id}', 'show');
            Route::put('invoice-type/{id}', 'update');
            Route::delete('invoice-type/delete/{id}', 'destroy');
        });
    Route::get('/permissions', [PermissionController::class, 'index']);
});
// Artisan commands routes
Route::controller(ArtisanController::class)->prefix('php')->group(function () {
    Route::get('runComposerDump', 'runComposerDump'); // عمل اوتو لود للملفات 
    Route::get('generateBackup', 'generateBackup'); //  توليد السيدرز الاحتياطية
    Route::get('migrateAndSeed', 'migrateAndSeed'); // ميجريشن ريفرش وعمل سيدرنج لقاعدة البيانات من جديد
    Route::get('applyBackup', 'applyBackup');     //  تطبيق السيدرز الاحتياطية
    Route::get('PermissionsSeeder', 'PermissionsSeeder'); // تشغيل PermissionsSeeder
    Route::get('DatabaseSeeder', 'DatabaseSeeder'); // تشغيل DatabaseSeeder
    Route::get('seedRolesAndPermissions', 'seedRolesAndPermissions'); // تشغيل RolesAndPermissionsSeeder
    Route::get('clear', 'clearAllCache'); // مسح جميع الكاشات وإعادة بنائها
});
