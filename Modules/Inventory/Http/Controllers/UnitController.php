<?php
// متحكم إدارة وحدات القياس مع دعم CRUD الكامل
namespace Modules\Inventory\Http\Controllers;

use App\Http\Controllers\Controller;
use Modules\Inventory\Http\Requests\StoreUnitRequest;
use Modules\Inventory\Http\Requests\UpdateUnitRequest;
use Modules\Inventory\Http\Resources\UnitResource;
use Modules\Inventory\Http\Resources\UnitGroupResource;
use Modules\Inventory\Http\Resources\UnitConversionResource;
use Modules\Inventory\Models\Unit;
use Modules\Inventory\Models\UnitGroup;
use Modules\Inventory\Models\UnitConversion;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Throwable;

class UnitController extends Controller
{
    /**
     * عرض قائمة وحدات القياس المتاحة
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $query = Unit::with('group');

            if (!$request->boolean('with_inactive', false)) {
                $query->where('is_active', true);
            }

            if ($request->filled('unit_group_id')) {
                $query->where('unit_group_id', $request->unit_group_id);
            }

            if ($request->filled('company_id')) {
                $query->where('company_id', $request->company_id);
            }

            $units = $query->orderBy('name')->get();

            return api_success(UnitResource::collection($units), 'تم استرداد وحدات القياس بنجاح.');
        } catch (Throwable $e) {
            return api_exception($e);
        }
    }

    /**
     * إنشاء وحدة قياس جديدة
     */
    public function store(StoreUnitRequest $request): JsonResponse
    {
        try {
            $companyId = auth()->user()?->active_company_id;

            $unit = Unit::create([
                'unit_group_id'  => $request->unit_group_id,
                'name'           => $request->name,
                'code'           => $request->code,
                'decimal_places' => $request->decimal_places ?? 0,
                'is_active'      => $request->boolean('is_active', true),
                'company_id'     => $companyId,
                'created_by'     => auth()->id(),
            ]);

            return api_success(new UnitResource($unit->load('group')), 'تم إضافة وحدة القياس بنجاح.');
        } catch (Throwable $e) {
            return api_exception($e);
        }
    }

    /**
     * عرض وحدة قياس محددة
     */
    public function show(Unit $unit): JsonResponse
    {
        try {
            return api_success(new UnitResource($unit->load('group')), 'تم استرداد وحدة القياس.');
        } catch (Throwable $e) {
            return api_exception($e);
        }
    }

    /**
     * تحديث وحدة قياس
     */
    public function update(UpdateUnitRequest $request, Unit $unit): JsonResponse
    {
        try {
            // منع تعديل الوحدات العامة للسيستم من غير السوبر آدمن
            if (is_null($unit->company_id) && !auth()->user()->hasPermissionTo(perm_key('admin.super'))) {
                return api_error('لا يمكن تعديل وحدات النظام الأساسية.', 403);
            }

            $unit->update($request->validated());

            return api_success(new UnitResource($unit->load('group')), 'تم تحديث وحدة القياس بنجاح.');
        } catch (Throwable $e) {
            return api_exception($e);
        }
    }

    /**
     * حذف وحدة قياس (منع حذف الوحدات المستخدمة)
     */
    public function destroy(Unit $unit): JsonResponse
    {
        try {
            // منع حذف الوحدات العامة للسيستم
            if (is_null($unit->company_id)) {
                return api_error('لا يمكن حذف وحدات النظام الأساسية.', 403);
            }

            // التحقق من عدم استخدام الوحدة في منتجات
            $usedInProducts = \DB::table('products')
                ->where(function ($q) use ($unit) {
                    $q->where('base_unit_id', $unit->id)
                      ->orWhere('purchase_unit_id', $unit->id)
                      ->orWhere('display_unit_id', $unit->id);
                })->exists();

            if ($usedInProducts) {
                return api_error('لا يمكن حذف هذه الوحدة لأنها مرتبطة بمنتجات موجودة.', 422);
            }

            $unit->delete();

            return api_success(null, 'تم حذف وحدة القياس بنجاح.');
        } catch (Throwable $e) {
            return api_exception($e);
        }
    }

    /**
     * جلب كل مجموعات الوحدات
     */
    public function groups(): JsonResponse
    {
        try {
            $groups = UnitGroup::with('units')->orderBy('name')->get();
            return api_success(UnitGroupResource::collection($groups), 'تم استرداد مجموعات الوحدات.');
        } catch (Throwable $e) {
            return api_exception($e);
        }
    }

    /**
     * جلب تحويلات الوحدات
     */
    public function conversions(Request $request): JsonResponse
    {
        try {
            $query = UnitConversion::with(['fromUnit', 'toUnit', 'group']);

            if ($request->filled('unit_group_id')) {
                $query->where('unit_group_id', $request->unit_group_id);
            }

            $conversions = $query->get();
            return api_success(UnitConversionResource::collection($conversions), 'تم استرداد التحويلات.');
        } catch (Throwable $e) {
            return api_exception($e);
        }
    }

    /**
     * إضافة تحويل وحدات جديد
     */
    public function storeConversion(Request $request): JsonResponse
    {
        $request->validate([
            'unit_group_id' => 'required|exists:unit_groups,id',
            'from_unit_id'  => 'required|exists:units,id',
            'to_unit_id'    => 'required|exists:units,id|different:from_unit_id',
            'factor'        => 'required|numeric|min:0.000001',
        ]);

        try {
            $companyId = auth()->user()?->active_company_id;

            $conversion = UnitConversion::firstOrCreate(
                [
                    'unit_group_id' => $request->unit_group_id,
                    'from_unit_id'  => $request->from_unit_id,
                    'to_unit_id'    => $request->to_unit_id,
                ],
                [
                    'factor'         => $request->factor,
                    'reverse_factor' => 1 / $request->factor,
                    'company_id'     => $companyId,
                    'created_by'     => auth()->id(),
                ]
            );

            if (!$conversion->wasRecentlyCreated) {
                $conversion->update([
                    'factor'         => $request->factor,
                    'reverse_factor' => 1 / $request->factor,
                ]);
            }

            return api_success(new UnitConversionResource($conversion->load(['fromUnit', 'toUnit', 'group'])), 'تم حفظ قاعدة التحويل.');
        } catch (Throwable $e) {
            return api_exception($e);
        }
    }

    /**
     * حذف تحويل وحدات
     */
    public function destroyConversion(UnitConversion $conversion): JsonResponse
    {
        try {
            if (is_null($conversion->company_id)) {
                return api_error('لا يمكن حذف تحويلات النظام الأساسية.', 403);
            }
            $conversion->delete();
            return api_success(null, 'تم حذف قاعدة التحويل.');
        } catch (Throwable $e) {
            return api_exception($e);
        }
    }
}
