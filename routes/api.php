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
            Route::get('users/search', 'usersSearch');
            Route::get('users/search-advanced', 'indexWithSearch');
            Route::post('user', 'store');
            Route::get('user/{user}', 'show');
            Route::put('user/{user}', 'update');
            Route::put('change-company/{user}', 'changeCompany');
            Route::put('user/{user}/cashbox/{cashBoxId}/set-default', 'setDefaultCashBox');
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

    // Image Controller 
    Route::controller(ImageController::class)
        ->group(function () {
            Route::get('images', 'index');
            Route::post('image', 'store');
            Route::put('image/{Image}', 'update');
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
            Route::post('invoice', 'store');
            Route::get('invoice/{invoice}', 'show');
            Route::put('invoice/{invoice}', 'update');
            Route::delete('invoice/{invoice}', 'destroy');
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
            Route::post('role', 'store');
            Route::get('role/{role}', 'show');
            Route::put('role/{role}', 'update');
            Route::delete('role/{role}', 'destroy');
            Route::post('role/assignRole', 'assignRole');
        });
    // cashBoxTypes Controller
    Route::controller(CashBoxTypeController::class)
        ->group(function () {
            Route::get('cashBoxTypes', 'index');
            Route::post('cashBoxType', 'store');
            Route::get('cashBoxType/{cashBoxType}', 'show');
            Route::put('cashBoxType/{cashBoxType}', 'update');
            Route::patch('cashBoxType/{id}/toggle', 'toggle');
            Route::delete('cashBoxType/{cashBoxType}', 'destroy');
        });
    // CashBox Controller
    Route::controller(CashBoxController::class)
        ->group(function () {
            Route::get('cashBoxs', 'index');
            Route::post('cashBox', 'store');
            Route::get('cashBox/{cashBox}', 'show');
            Route::put('cashBox/{cashBox}', 'update');
            Route::delete('cashBox/{cashBox}', 'destroy');
            Route::post('cashBox/transfer', 'transferFunds');
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
            Route::post('product', 'store');
            Route::get('product/{product}', 'show');
            Route::put('product/{product}', 'update');
            Route::delete('product/delete/{product}', 'destroy');
        });
    // Attribute Controller
    Route::controller(AttributeController::class)
        ->group(function () {
            Route::get('attributes', 'index');
            Route::post('attribute', 'store');
            Route::get('attribute/{attribute}', 'show');
            Route::put('attribute/{attribute}', 'update');
            Route::delete('attribute/{attribute}', 'destroy');
            Route::post('attribute/deletes', 'deleteMultiple');
        });
    // Attribute Value Controller
    Route::controller(AttributeValueController::class)
        ->group(function () {
            Route::get('attribute-values', 'index');
            Route::post('attribute-value', 'store');
            Route::get('attribute-value/{attributeValue}', 'show');
            Route::put('attribute-value/{attributeValue}', 'update');
            Route::delete('attribute-value/{attributeValue}', 'destroy');
            Route::post('attribute-value/deletes', 'deleteMultiple');
        });
    // Product Variant Controller
    Route::controller(ProductVariantController::class)
        ->group(function () {
            Route::get('product-variants', 'index');
            Route::post('product-variant', 'store');
            Route::get('product-variant/{productVariant}', 'show');
            Route::put('product-variant/{productVariant}', 'update');
            Route::delete('product-variant/{productVariant}', 'destroy');
            Route::post('product-variant/delete', 'deleteMultiple');
            Route::get('product-variants/search-by-product', 'searchByProduct');
        });
    // Warehouse Controller
    Route::controller(WarehouseController::class)
        ->group(function () {
            Route::get('warehouses', 'index');
            Route::post('warehouse', 'store');
            Route::get('warehouse/{warehouse}', 'show');
            Route::put('warehouse/{warehouse}', 'update');
            Route::post('warehouse/delete', 'destroy');
        });
    // Stock Controller
    Route::controller(StockController::class)
        ->group(function () {
            Route::get('stocks', 'index');
            Route::post('stock', 'store');
            Route::get('stock/{stock}', 'show');
            Route::put('stock/{stock}', 'update');
            Route::post('stock/delete', 'destroy');
        });
    // Category Controller
    Route::controller(CategoryController::class)
        ->group(function () {
            Route::get('categories', 'index');
            Route::post('category', 'store');
            Route::get('category/{category}', 'show');
            Route::put('category/{category}', 'update');
            Route::post('category/delete', 'destroy');
        });
    // Brand Controller
    Route::controller(BrandController::class)
        ->group(function () {
            Route::get('brands', 'index');
            Route::post('brand', 'store');
            Route::get('brand/{brand}', 'show');
            Route::put('brand/{brand}', 'update');
            Route::delete('brand/delete/{brand}', 'destroy');
        });
    // InvoiceType Controller
    Route::controller(InvoiceTypeController::class)->group(function () {
        Route::get('invoice-types', 'index');
        Route::post('invoice-type', 'store');
        Route::get('invoice-type/{invoiceType}', 'show');
        Route::put('invoice-type/{invoiceType}', 'update');
        Route::delete('invoice-type/{invoiceType}', 'destroy');
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
        Route::post('payment', 'store');
        Route::get('payment/{payment}', 'show');
        Route::put('payment/{payment}', 'update');
        Route::delete('payment/{payment}', 'destroy');
    });
    // PaymentMethod Controller
    Route::controller(PaymentMethodController::class)
        ->group(function () {
            Route::get('payment-methods', 'index');
            Route::post('payment-method', 'store');

            Route::get('payment-method/{paymentMethod}', 'show');
            Route::put('payment-method/{paymentMethod}', 'update');
            Route::patch('payment-method/{id}/toggle', 'toggle');
            Route::delete('payment-method/delete/{paymentMethod}', 'destroy');
        });
    Route::controller(InstallmentPaymentController::class)
        ->group(function () {
            Route::get('installment-payments', 'index');
            Route::post('installment-payment', 'store');
            Route::post('installment-payment/pay', 'payInstallments');
            Route::get('installment-payment/{installmentPayment}', 'show');
            Route::put('installment-payment/{installmentPayment}', 'update');
            Route::delete('installment-payment/delete/{installmentPayment}', 'destroy');
        });
    // Revenue Controller
    Route::controller(RevenueController::class)
        ->group(function () {
            Route::get('revenues', 'index');
            Route::post('revenue', 'store');
            Route::get('revenue/{revenue}', 'show');
            Route::put('revenue/{revenue}', 'update');
            Route::delete('revenue/delete/{revenue}', 'destroy');
        });
    // Profit Controller
    Route::controller(ProfitController::class)
        ->group(function () {
            Route::get('profits', 'index');
            Route::post('profit', 'store');
            Route::get('profit/{profit}', 'show');
            Route::put('profit/{profit}', 'update');
            Route::delete('profit/delete/{profit}', 'destroy');
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
