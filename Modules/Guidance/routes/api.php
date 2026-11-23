<?php

use Illuminate\Support\Facades\Route;
use Modules\Guidance\Http\Controllers\GuidanceController;

Route::middleware(['auth:sanctum'])->prefix('v1')->group(function () {
    Route::prefix('guidance')->group(function () {
        // Reuse domain controllers behind an additional setup-specific permission boundary.
        foreach([
            'brands'=>[\Modules\Inventory\Http\Controllers\BrandController::class,'brands'],
            'categories'=>[\Modules\Inventory\Http\Controllers\CategoryController::class,'categories'],
            'units'=>[\Modules\Inventory\Http\Controllers\UnitController::class,'units'],
            'unit-groups'=>[\Modules\Inventory\Http\Controllers\UnitGroupController::class,'units'],
            'unit-conversions'=>[\Modules\Inventory\Http\Controllers\UnitConversionController::class,'units'],
            'warehouses'=>[\Modules\Inventory\Http\Controllers\WarehouseController::class,'warehouses'],
            'branches'=>[\Modules\Companies\Http\Controllers\BranchController::class,'branches'],
            'mail-settings'=>[\Modules\Notification\Http\Controllers\MailSettingController::class,'mail_settings'],
            'whatsapp-settings'=>[\Modules\Notification\Http\Controllers\WhatsAppSettingController::class,'whatsapp_settings'],
            'notification-templates'=>[\Modules\Notification\Http\Controllers\NotificationTemplateController::class,'notification_templates'],
            'notification-workflows'=>[\Modules\Notification\Http\Controllers\NotificationWorkflowController::class,'notification_workflows'],
            'payment-methods'=>[\App\Http\Controllers\PaymentMethodController::class,'payment_methods'],
            'cash-boxes'=>[\Modules\Accounting\Http\Controllers\CashBoxController::class,'cash_boxes'],
            'users'=>[\App\Http\Controllers\UserController::class,'users'],
            'roles'=>[\App\Http\Controllers\RoleController::class,'roles'],
            'invoice-types'=>[\App\Http\Controllers\InvoiceTypeController::class,'invoice_types'],
        ] as $resource=>[$controller,$permission]){
            $middleware=[\Modules\Guidance\Http\Middleware\SetupResourceAccess::class.':'.$permission];
            if(in_array($resource,['brands','categories','units','unit-groups','unit-conversions','warehouses']))$middleware=[...$middleware,'scope_company','branch_context'];
            $names=[];foreach(['index','show','store','update'] as $action)$names[$action]='guidance.setup.resources.'.$resource.'.'.$action;
            Route::apiResource('setup/resources/'.$resource,$controller)->except(['destroy'])->names($names)->middleware($middleware);
        }
        Route::get('setup/compact', [\Modules\Guidance\Http\Controllers\CompanySetupController::class, 'compact']);
        Route::put('setup/compact/{step}', [\Modules\Guidance\Http\Controllers\CompanySetupController::class, 'saveCompact']);
        Route::put('setup/options/{section}', [\Modules\Guidance\Http\Controllers\CompanySetupController::class, 'option']);
        Route::get('setup', [\Modules\Guidance\Http\Controllers\CompanySetupController::class, 'show']);
        Route::put('setup/steps/{step}', [\Modules\Guidance\Http\Controllers\CompanySetupController::class, 'save']);
        Route::patch('setup/preferences', [\Modules\Guidance\Http\Controllers\CompanySetupController::class, 'preferences']);
        Route::get('progress', [GuidanceController::class, 'index'])->name('guidance.progress');
        Route::post('complete', [GuidanceController::class, 'complete'])->name('guidance.complete');
        Route::post('uncomplete', [GuidanceController::class, 'uncomplete'])->name('guidance.uncomplete');
        Route::post('reset', [GuidanceController::class, 'reset'])->name('guidance.reset');
    });
});

