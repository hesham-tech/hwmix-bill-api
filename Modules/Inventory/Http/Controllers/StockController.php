<?php

namespace Modules\Inventory\Http\Controllers;

use App\Http\Controllers\Controller;
use Modules\Inventory\Http\Requests\StoreStockRequest;
use Modules\Inventory\Http\Requests\UpdateStockRequest;
use Modules\Inventory\Http\Resources\StockResource;
use Modules\Inventory\Models\Stock;
use Modules\Inventory\Models\ProductVariant;
use Modules\Inventory\Models\Warehouse;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * @group إدارة المخزون (Module Inventory)
 */
class StockController extends Controller
{
    protected array $relations = ['creator', 'company', 'variant.product', 'warehouse'];

    /**
     * عرض قائمة حركات المخزون
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $authUser = Auth::user();
            $query = Stock::query()->with($this->relations);

            if (!$authUser->hasPermissionTo(perm_key('admin.super'))) {
                $query->whereCompanyIsCurrent();
            }

            if ($request->filled('variant_id')) {
                $query->where('variant_id', $request->variant_id);
            }
            if ($request->filled('warehouse_id')) {
                $query->where('warehouse_id', $request->warehouse_id);
            }
            if ($request->filled('status')) {
                $query->where('status', $request->status);
            }

            $perPage = max(1, (int) $request->get('per_page', 20));
            $stocks = $query->orderBy('created_at', 'desc')->paginate($perPage);

            return api_success(StockResource::collection($stocks), 'تم جلب سجلات المخزون بنجاح.');
        } catch (Throwable $e) {
            return api_exception($e);
        }
    }

    /**
     * إضافة حركة مخزون
     */
    public function store(StoreStockRequest $request): JsonResponse
    {
        try {
            /** @var \App\Models\User $authUser */
            $authUser = Auth::user();
            $companyId = $authUser->active_company_id;

            DB::beginTransaction();
            
            // التحقق من الشركة
            ProductVariant::where('id', $request->variant_id)->where('company_id', $companyId)->firstOrFail();
            Warehouse::where('id', $request->warehouse_id)->where('company_id', $companyId)->firstOrFail();

            $stock = Stock::create(array_merge($request->validated(), [
                'created_by' => $authUser->id,
                'company_id' => $companyId
            ]));

            DB::commit();
            $stock->load($this->relations);
            return api_success(new StockResource($stock), 'تم إنشاء سجل المخزون بنجاح.', 201);
        } catch (Throwable $e) {
            DB::rollBack();
            return api_exception($e);
        }
    }

    /**
     * عرض سجل مخزون
     */
    public function show(Stock $stock): JsonResponse
    {
        try {
            $stock->load($this->relations);
            return api_success(new StockResource($stock), 'تم استرداد سجل المخزون بنجاح.');
        } catch (Throwable $e) {
            return api_exception($e);
        }
    }

    /**
     * تحديث سجل مخزون
     */
    public function update(UpdateStockRequest $request, Stock $stock): JsonResponse
    {
        try {
            $stock->update(array_merge($request->validated(), [
                'updated_by' => Auth::id()
            ]));
            $stock->load($this->relations);
            return api_success(new StockResource($stock), 'تم تحديث سجل المخزون بنجاح.');
        } catch (Throwable $e) {
            return api_exception($e);
        }
    }

    /**
     * حذف سجل مخزون
     */
    public function destroy(Stock $stock): JsonResponse
    {
        try {
            $stock->delete();
            return api_success([], 'تم حذف سجل المخزون بنجاح.');
        } catch (Throwable $e) {
            return api_exception($e);
        }
    }
}
