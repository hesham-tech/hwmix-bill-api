<?php
// متحكم إدارة مجموعات وحدات القياس مع دعم Builder الموحد والـ Templates والإضافة الفردية
namespace Modules\Inventory\Http\Controllers;

use App\Http\Controllers\Controller;
use Modules\Inventory\Http\Requests\StoreUnitGroupRequest;
use Modules\Inventory\Http\Requests\UpdateUnitGroupRequest;
use Modules\Inventory\Http\Requests\StoreUnitGroupBuilderRequest;
use Modules\Inventory\Http\Resources\UnitGroupResource;
use Modules\Inventory\Models\UnitGroup;
use Modules\Inventory\Models\Unit;
use Modules\Inventory\Models\UnitConversion;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Throwable;

class UnitGroupController extends Controller
{
    /**
     * عرض قائمة مجموعات الوحدات (مجموعات الشركة + System Groups للسوبر أدمن)
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $authUser = Auth::user();
            $query = UnitGroup::with(['units', 'conversions.fromUnit', 'conversions.toUnit']);

            if (!$authUser->hasPermissionTo(perm_key('admin.super'))) {
                $query->whereCompanyIsCurrent();
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
     * عرض مجموعات الشركة فقط (للـ Cards — بدون System Groups)
     */
    public function indexCompanyOnly(Request $request): JsonResponse
    {
        try {
            $query = UnitGroup::with(['units', 'conversions.fromUnit', 'conversions.toUnit'])
                ->whereCompanyIsCurrent();

            if ($request->filled('search')) {
                $query->where('name', 'like', '%' . $request->search . '%');
            }

            $groups = $query->orderBy('name')->get();

            return api_success(UnitGroupResource::collection($groups), 'تم استرداد مجموعات الشركة بنجاح.');
        } catch (Throwable $e) {
            return api_exception($e);
        }
    }

    /**
     * إنشاء مجموعة وحدات كاملة (مجموعة + وحدات + تحويلات) داخل Transaction واحدة
     * يُستخدم من Wizard الإنشاء أو عند نسخ Template
     */
    public function buildWithUnitsAndConversions(StoreUnitGroupBuilderRequest $request): JsonResponse
    {
        try {
            $companyId = Auth::user()?->active_company_id;
            $userId    = Auth::id();

            $group = DB::transaction(function () use ($request, $companyId, $userId) {

                // 1. إنشاء المجموعة
                $group = UnitGroup::create([
                    'name'       => $request->name,
                    'type'       => $request->type,
                    'company_id' => $companyId,
                    'created_by' => $userId,
                ]);

                // 2. إنشاء الوحدات وتخزين الـ temp_uuid → id
                $uuidToId = [];
                foreach ($request->units as $unitData) {
                    $unit = Unit::create([
                        'unit_group_id'  => $group->id,
                        'name'           => $unitData['name'],
                        'code'           => $unitData['code'],
                        'decimal_places' => $unitData['decimal_places'] ?? 0,
                        'is_active'      => $unitData['is_active'] ?? true,
                        'company_id'     => $companyId,
                        'created_by'     => $userId,
                    ]);
                    $uuidToId[$unitData['temp_uuid']] = $unit->id;
                }

                // 3. إنشاء التحويلات (اختيارية)
                foreach ($request->conversions ?? [] as $convData) {
                    $fromId = $uuidToId[$convData['from_unit_temp_uuid']] ?? null;
                    $toId   = $uuidToId[$convData['to_unit_temp_uuid']] ?? null;

                    if (!$fromId || !$toId || $fromId === $toId) {
                        continue;
                    }

                    $factor = (float) $convData['factor'];
                    UnitConversion::create([
                        'unit_group_id'  => $group->id,
                        'from_unit_id'   => $fromId,
                        'to_unit_id'     => $toId,
                        'factor'         => $factor,
                        'reverse_factor' => $factor > 0 ? (1 / $factor) : 0,
                        'company_id'     => $companyId,
                        'created_by'     => $userId,
                    ]);
                }

                return $group;
            });

            $group->load(['units', 'conversions.fromUnit', 'conversions.toUnit']);

            return api_success(new UnitGroupResource($group), 'تم إنشاء مجموعة الوحدات بنجاح.');
        } catch (Throwable $e) {
            return api_exception($e);
        }
    }

    /**
     * جلب System Groups كـ Templates جاهزة للاستخدام في Wizard
     */
    public function systemTemplates(): JsonResponse
    {
        try {
            $templates = UnitGroup::with(['units', 'conversions.fromUnit', 'conversions.toUnit'])
                ->whereNull('company_id')
                ->orderBy('name')
                ->get();

            return api_success(UnitGroupResource::collection($templates), 'تم استرداد القوالب الجاهزة بنجاح.');
        } catch (Throwable $e) {
            return api_exception($e);
        }
    }

    /**
     * إضافة وحدة جديدة لمجموعة موجودة (من Detail Drawer)
     */
    public function addUnit(Request $request, UnitGroup $unitGroup): JsonResponse
    {
        $request->validate([
            'name'           => 'required|string|max:100',
            'code'           => 'required|string|max:20',
            'decimal_places' => 'required|integer|min:0|max:6',
            'is_active'      => 'boolean',
        ]);

        try {
            // منع التعديل على مجموعات النظام من غير السوبر أدمن
            if (is_null($unitGroup->company_id) && !Auth::user()->hasPermissionTo(perm_key('admin.super'))) {
                return api_error('لا يمكن إضافة وحدات لمجموعات النظام الأساسية.', [], 403);
            }

            $companyId = Auth::user()?->active_company_id;

            $unit = Unit::create([
                'unit_group_id'  => $unitGroup->id,
                'name'           => $request->name,
                'code'           => $request->code,
                'decimal_places' => $request->decimal_places ?? 0,
                'is_active'      => $request->boolean('is_active', true),
                'company_id'     => $companyId,
                'created_by'     => Auth::id(),
            ]);

            return api_success(
                new \Modules\Inventory\Http\Resources\UnitResource($unit->load('group')),
                'تم إضافة الوحدة بنجاح.'
            );
        } catch (Throwable $e) {
            return api_exception($e);
        }
    }

    /**
     * إضافة قاعدة تحويل جديدة لمجموعة موجودة (من Detail Drawer)
     */
    public function addConversion(Request $request, UnitGroup $unitGroup): JsonResponse
    {
        $request->validate([
            'from_unit_id' => 'required|exists:units,id',
            'to_unit_id'   => 'required|exists:units,id|different:from_unit_id',
            'factor'       => 'required|numeric|min:0.000001',
        ]);

        try {
            if (is_null($unitGroup->company_id) && !Auth::user()->hasPermissionTo(perm_key('admin.super'))) {
                return api_error('لا يمكن إضافة تحويلات لمجموعات النظام الأساسية.', [], 403);
            }

            // التحقق أن الوحدتين تنتميان لنفس المجموعة
            $unitsInGroup = Unit::where('unit_group_id', $unitGroup->id)
                ->whereIn('id', [$request->from_unit_id, $request->to_unit_id])
                ->count();

            if ($unitsInGroup < 2) {
                return api_error('يجب أن تنتمي الوحدتين لنفس المجموعة.', [], 422);
            }

            $companyId = Auth::user()?->active_company_id;
            $factor    = (float) $request->factor;

            $conversion = UnitConversion::firstOrCreate(
                [
                    'unit_group_id' => $unitGroup->id,
                    'from_unit_id'  => $request->from_unit_id,
                    'to_unit_id'    => $request->to_unit_id,
                ],
                [
                    'factor'         => $factor,
                    'reverse_factor' => $factor > 0 ? (1 / $factor) : 0,
                    'company_id'     => $companyId,
                    'created_by'     => Auth::id(),
                ]
            );

            if (!$conversion->wasRecentlyCreated) {
                $conversion->update([
                    'factor'         => $factor,
                    'reverse_factor' => $factor > 0 ? (1 / $factor) : 0,
                ]);
            }

            return api_success(
                new \Modules\Inventory\Http\Resources\UnitConversionResource(
                    $conversion->load(['fromUnit', 'toUnit', 'group'])
                ),
                'تم إضافة قاعدة التحويل بنجاح.'
            );
        } catch (Throwable $e) {
            return api_exception($e);
        }
    }

    /**
     * إضافة مجموعة وحدات جديدة (بسيطة — للتوافق مع الـ API القديم)
     */
    public function store(StoreUnitGroupRequest $request): JsonResponse
    {
        try {
            $companyId = Auth::user()?->active_company_id;

            $group = UnitGroup::create([
                'name'       => $request->name,
                'type'       => $request->type,
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
            $unitGroup->load(['units', 'conversions.fromUnit', 'conversions.toUnit']);
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
            if (is_null($unitGroup->company_id) && !Auth::user()->hasPermissionTo(perm_key('admin.super'))) {
                return api_error('لا يمكن تعديل مجموعات النظام الأساسية.', [], 403);
            }

            $unitGroup->update($request->validated());

            return api_success(new UnitGroupResource($unitGroup->load(['units', 'conversions.fromUnit', 'conversions.toUnit'])), 'تم تحديث مجموعة الوحدات بنجاح.');
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
            if (is_null($unitGroup->company_id)) {
                return api_error('لا يمكن حذف مجموعات النظام الأساسية.', [], 403);
            }

            if ($unitGroup->units()->exists()) {
                return api_error('لا يمكن حذف مجموعة الوحدات لوجود وحدات قياس مرتبطة بها. احذف الوحدات أولاً.', [], 422);
            }

            $unitGroup->delete();

            return api_success(null, 'تم حذف مجموعة الوحدات بنجاح.');
        } catch (Throwable $e) {
            return api_exception($e);
        }
    }
}
