<?php
// متحكم إدارة تحويلات وحدات القياس
namespace Modules\Inventory\Http\Controllers;

use App\Http\Controllers\Controller;
use Modules\Inventory\Http\Requests\StoreUnitConversionRequest;
use Modules\Inventory\Http\Requests\UpdateUnitConversionRequest;
use Modules\Inventory\Http\Resources\UnitConversionResource;
use Modules\Inventory\Models\UnitConversion;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Throwable;

class UnitConversionController extends Controller
{
    /**
     * عرض قائمة تحويلات الوحدات
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $authUser = Auth::user();
            $query = UnitConversion::with(['fromUnit', 'toUnit', 'group']);

            if (!$authUser->hasPermissionTo(perm_key('admin.super'))) {
                $query->where(function ($q) {
                    $q->whereCompanyIsCurrent()->orWhereNull('company_id');
                });
            }

            if ($request->filled('unit_group_id')) {
                $query->where('unit_group_id', $request->unit_group_id);
            }

            $conversions = $query->get();

            return api_success(UnitConversionResource::collection($conversions), 'تم استرداد التحويلات بنجاح.');
        } catch (Throwable $e) {
            return api_exception($e);
        }
    }

    /**
     * إضافة تحويل جديد أو تحديثه إذا كان موجوداً
     */
    public function store(StoreUnitConversionRequest $request): JsonResponse
    {
        try {
            $companyId = Auth::user()?->active_company_id;

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
                    'created_by'     => Auth::id(),
                ]
            );

            if (!$conversion->wasRecentlyCreated) {
                // منع التعديل إذا كانت قاعدة التحويل تابعة للنظام الأساسي وليست للشركة الحالية
                if (is_null($conversion->company_id) && !Auth::user()->hasPermissionTo(perm_key('admin.super'))) {
                    return api_error('لا يمكن تعديل قواعد تحويل النظام الأساسية.', 403);
                }

                $conversion->update([
                    'factor'         => $request->factor,
                    'reverse_factor' => 1 / $request->factor,
                ]);
            }

            return api_success(new UnitConversionResource($conversion->load(['fromUnit', 'toUnit', 'group'])), 'تم حفظ قاعدة التحويل بنجاح.');
        } catch (Throwable $e) {
            return api_exception($e);
        }
    }

    /**
     * عرض تفاصيل قاعدة تحويل
     */
    public function show(UnitConversion $unitConversion): JsonResponse
    {
        try {
            $unitConversion->load(['fromUnit', 'toUnit', 'group']);
            return api_success(new UnitConversionResource($unitConversion), 'تم استرداد قاعدة التحويل بنجاح.');
        } catch (Throwable $e) {
            return api_exception($e);
        }
    }

    /**
     * تحديث معامل التحويل لقاعدة قائمة
     */
    public function update(UpdateUnitConversionRequest $request, UnitConversion $unitConversion): JsonResponse
    {
        try {
            // منع تعديل التحويلات العامة للسيستم
            if (is_null($unitConversion->company_id) && !Auth::user()->hasPermissionTo(perm_key('admin.super'))) {
                return api_error('لا يمكن تعديل قواعد تحويل النظام الأساسية.', 403);
            }

            $unitConversion->update([
                'factor'         => $request->factor,
                'reverse_factor' => 1 / $request->factor,
            ]);

            return api_success(new UnitConversionResource($unitConversion->load(['fromUnit', 'toUnit', 'group'])), 'تم تحديث قاعدة التحويل بنجاح.');
        } catch (Throwable $e) {
            return api_exception($e);
        }
    }

    /**
     * حذف قاعدة تحويل
     */
    public function destroy(UnitConversion $unitConversion): JsonResponse
    {
        try {
            // منع حذف تحويلات النظام الأساسية
            if (is_null($unitConversion->company_id)) {
                return api_error('لا يمكن حذف تحويلات النظام الأساسية.', 403);
            }

            $unitConversion->delete();

            return api_success(null, 'تم حذف قاعدة التحويل بنجاح.');
        } catch (Throwable $e) {
            return api_exception($e);
        }
    }
}
