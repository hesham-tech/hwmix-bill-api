<?php

namespace Modules\Inventory\Http\Controllers;

use App\Http\Controllers\Controller;
use Modules\Inventory\Http\Requests\StoreWarehouseRequest;
use Modules\Inventory\Http\Requests\UpdateWarehouseRequest;
use Modules\Inventory\Http\Resources\WarehouseResource;
use Modules\Inventory\Models\Warehouse;
use Modules\Inventory\Actions\CreateWarehouseAction;
use Modules\Inventory\Actions\UpdateWarehouseAction;
use Modules\Inventory\Actions\DeleteWarehouseAction;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Throwable;

/**
 * @group إدارة المستودعات (Module Inventory)
 * إدارة مستودعات الشركة (V1)
 */
class WarehouseController extends Controller
{
    protected array $relations = ['company', 'creator', 'stocks.variant.product'];

    /**
     * عرض قائمة المستودعات
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $authUser = Auth::user();
            $query = Warehouse::query()->with($this->relations);

            // تطبيق الصلاحيات (Scope)
            if ($authUser->hasPermissionTo(perm_key('admin.super'))) {
                // المسؤول العام يرى الكل
            } elseif ($authUser->hasAnyPermission([perm_key('warehouses.view_all'), perm_key('admin.company')])) {
                $query->whereCompanyIsCurrent();
            } elseif ($authUser->hasPermissionTo(perm_key('warehouses.view_children'))) {
                $query->whereCompanyIsCurrent()->whereCreatedByUserOrChildren();
            } elseif ($authUser->hasPermissionTo(perm_key('warehouses.view_self'))) {
                $query->whereCompanyIsCurrent()->whereCreatedByUser();
            } else {
                return api_forbidden('ليس لديك إذن لعرض المستودعات.');
            }

            // فلاتر البحث
            if ($request->filled('search')) {
                $searchTerm = $request->input('search');
                $query->where(function ($q) use ($searchTerm) {
                    $q->where('name', 'like', '%' . $searchTerm . '%')
                        ->orWhere('location', 'like', '%' . $searchTerm . '%');
                });
            }

            $perPage = (int) $request->get('per_page', 20);
            $warehouses = $perPage == -1 ? $query->get() : $query->paginate($perPage);

            return api_success(WarehouseResource::collection($warehouses), 'تم جلب المستودعات بنجاح.');
        } catch (Throwable $e) {
            return api_exception($e);
        }
    }

    /**
     * إنشاء مستودع جديد
     */
    public function store(StoreWarehouseRequest $request, CreateWarehouseAction $action): JsonResponse
    {
        try {
            $warehouse = $action->handle($request->validated());
            $warehouse->load($this->relations);
            return api_success(new WarehouseResource($warehouse), 'تم إنشاء المستودع بنجاح.', 201);
        } catch (Throwable $e) {
            return api_exception($e);
        }
    }

    /**
     * عرض تفاصيل مستودع
     */
    public function show(Warehouse $warehouse): JsonResponse
    {
        try {
            $authUser = Auth::user();
            if (!$authUser->hasPermissionTo(perm_key('admin.super')) && $warehouse->company_id !== $authUser->active_company_id) {
                return api_forbidden('ليس لديك صلاحية للوصول إلى هذا المستودع.');
            }
            $warehouse->load($this->relations);
            return api_success(new WarehouseResource($warehouse), 'تم استرداد المستودع بنجاح.');
        } catch (Throwable $e) {
            return api_exception($e);
        }
    }

    /**
     * تحديث بيانات مستودع
     */
    public function update(UpdateWarehouseRequest $request, Warehouse $warehouse, UpdateWarehouseAction $action): JsonResponse
    {
        try {
            $authUser = Auth::user();
            if (!$authUser->hasPermissionTo(perm_key('admin.super')) && $warehouse->company_id !== $authUser->active_company_id) {
                return api_forbidden('ليس لديك صلاحية للوصول إلى هذا المستودع.');
            }
            $data = $request->validated();
            $data['warehouse'] = $warehouse;
            $updatedWarehouse = $action->handle($data);
            $updatedWarehouse->load($this->relations);
            return api_success(new WarehouseResource($updatedWarehouse), 'تم تحديث المستودع بنجاح.');
        } catch (Throwable $e) {
            return api_exception($e);
        }
    }

    /**
     * حذف مستودع
     */
    public function destroy(Warehouse $warehouse, DeleteWarehouseAction $action): JsonResponse
    {
        try {
            $action->handle(['warehouse' => $warehouse]);
            return api_success([], 'تم حذف المستودع بنجاح.');
        } catch (Throwable $e) {
            $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
            return api_exception($e, $code);
        }
    }

    /**
     * تعيين المستودع كافتراضي
     */
    public function setDefault(Warehouse $warehouse): JsonResponse
    {
        try {
            $authUser = Auth::user();
            if (!$authUser->hasPermissionTo(perm_key('admin.super')) && $warehouse->company_id !== $authUser->active_company_id) {
                return api_forbidden('ليس لديك صلاحية للوصول إلى هذا المستودع.');
            }
            $warehouse->update(['is_default' => true]);
            return api_success(new WarehouseResource($warehouse->fresh($this->relations)), 'تم تعيين المستودع كافتراضي بنجاح.');
        } catch (Throwable $e) {
            return api_exception($e);
        }
    }
}
