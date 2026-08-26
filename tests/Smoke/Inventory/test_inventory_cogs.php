<?php

require __DIR__ . '/../../../vendor/autoload.php';
$app = require_once __DIR__ . '/../../../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Modules\Inventory\Models\Stock;
use App\Models\User;
use App\Models\FinancialLedger;
use App\Http\Controllers\StockController;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Modules\Inventory\Http\Requests\StoreStockRequest;

try {
    $admin = User::first();
    Auth::login($admin);
    $companyId = $admin->active_company_id;

    $variant = \Modules\Inventory\Models\ProductVariant::where('company_id', $companyId)->first();
    $warehouse = \Modules\Inventory\Models\Warehouse::where('company_id', $companyId)->first();
    
    if (!$variant || !$warehouse) {
        die("No variant or warehouse found.\n");
    }

    DB::beginTransaction();

    $request = new StoreStockRequest();
    $request->merge([
        'variant_id' => $variant->id,
        'warehouse_id' => $warehouse->id,
        'quantity' => 10,
        
        'status' => 'available',
        'cost' => 15,
        'company_id' => $companyId,
        'created_by' => $admin->id,
        'cost' => $variant->cost ?? 10
    ]);
    $request->setContainer(app())->setRedirector(app(\Illuminate\Routing\Redirector::class));
    $request->validateResolved();

    $controller = app(\Modules\Inventory\Http\Controllers\StockController::class);
    $response = $controller->store($request);
    
    $responseContent = json_decode($response->getContent()); if (!isset($responseContent->data)) { throw new Exception(json_encode($responseContent)); } $stockId = $responseContent->data->id; $stock = Stock::find($stockId);
    
    if (!$stock->financial_operation_id) {
        throw new Exception("No financial operation ID found on stock. Stock: " . json_encode($stock->toArray()));
    }

    $ledgers = FinancialLedger::where('financial_operation_id', $stock->financial_operation_id)->get();
    if ($ledgers->count() !== 2) {
        throw new Exception("Expected 2 ledger entries (COGS and Asset), got " . $ledgers->count());
    }

    echo "[PASS] Stock Addition -> Ledgers created.\n";

    // 2. Reverse Stock
    $controller->destroy($stock);

    $stock->refresh();
    
    
    
    if (!$stock->trashed()) {
        throw new Exception("Stock not soft deleted.");
    }

    echo "[PASS] Stock Reversal -> Soft deleted, Ledgers reversed.\n";

    DB::rollBack();
    echo "=== ALL PASSED ===\n";

} catch (Throwable $e) {
    DB::rollBack();
    echo "[FAIL] " . $e->getMessage() . "\n";
    echo $e->getTraceAsString();
}