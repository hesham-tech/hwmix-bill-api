<?php
// متحكم إدارة مجموعات وحدات القياس
namespace Modules\Inventory\Http\Controllers;

use App\Http\Controllers\Controller;
use Modules\Inventory\Http\Requests\StoreUnitGroupRequest;
use Modules\Inventory\Http\Requests\UpdateUnitGroupRequest;
use Modules\Inventory\Http\Resources\UnitGroupResource;
use Modules\Inventory\Models\UnitGroup;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Throwable;

class UnitGroupController extends Controller
{
    /**
     * عرض قائمة مجموعات الوحدات
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $authUser = Auth::user();
            $query = UnitGroup::with('units');

            if (!$authUser->hasPermissionTo(perm_key('admin.super'))) {
                $query->where(function ($q) {
                    $q->whereCompanyIsCurrent()->orWhereNull('company_id');
                });
            }

            if ($request->filled('search')) {
                $query->where('name', 'like', '%' . $request->search . '%');
            }

            $groups = $query->orderBy('name')->get();

            return api_success(UnitGroupResource::collection($groups), 'تم استرداد مجموعات الوحدات بنجاح.');
        } catch (Throwable $e) {
            return api_exception($e);
        }
    }

    /**
     * إضافة مجموعة وحدات جديدة
     */
    public function store(StoreUnitGroupRequest $request): JsonResponse
    {
        try {
            $companyId = Auth::user()?->active_company_id;

            $group = UnitGroup::create([
                'name' => $request->name,
                'type' => $request->type,
                'company_id' => $companyId,
                'created_by' => Auth::id(),
            ]);

            return api_success(new UnitGroupResource($group), 'تم إضافة مجموعة الوحدات بنجاح.');
        } catch (Throwable $e) {
            return api_exception($e);
        }
    }

    /**
     * عرض تفاصيل مجموعة وحدات
     */
    public function show(UnitGroup $unitGroup): JsonResponse
    {
        try {
            $unitGroup->load('units');
            return api_success(new UnitGroupResource($unitGroup), 'تم استرداد مجموعة الوحدات بنجاح.');
        } catch (Throwable $e) {
            return api_exception($e);
        }
    }

    /**
     * تحديث بيانات مجموعة وحدات
     */
    public function update(UpdateUnitGroupRequest $request, UnitGroup $unitGroup): JsonResponse
    {
        try {
            // منع تعديل المجموعات العامة للسيستم من غير السوبر آدمن
            if (is_null($unitGroup->company_id) && !Auth::user()->hasPermissionTo(perm_key('admin.super'))) {
                return api_error('لا يمكن تعديل مجموعات النظام الأساسية.', 403);
            }

            $unitGroup->update($request->validated());

            return api_success(new UnitGroupResource($unitGroup->load('units')), 'تم تحديث مجموعة الوحدات بنجاح.');
        } catch (Throwable $e) {
            return api_exception($e);
        }
    }

    /**
     * حذف مجموعة وحدات
     */
    public function destroy(UnitGroup $unitGroup): JsonResponse
    {
        try {
            // منع حذف المجموعات العامة للسيستم
            if (is_null($unitGroup->company_id)) {
                return api_error('لا يمكن حذف مجموعات النظام الأساسية.', 403);
            }

            // تحقق من عدم وجود وحدات قياس مرتبطة
            if ($unitGroup->units()->exists()) {
                return api_error('لا يمكن حذف مجموعة الوحدات لوجود وحدات قياس مرتبطة بها.', 422);
            }

            $unitGroup->delete();

            return api_success(null, 'تم حذف مجموعة الوحدات بنجاح.');
        } catch (Throwable $e) {
            return api_exception($e);
        }
    }
}
