<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\CashBox;
use App\Models\CashBoxType;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse; // للتأكد من استيراد JsonResponse
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use App\Http\Resources\CashBoxType\CashBoxTypeResource;
use Illuminate\Validation\ValidationException;
use Throwable;


/**
 * Class CashBoxTypeController
 *
 * تحكم في أنواع الخزن (عرض، إضافة، تعديل، حذف)
 *
 * @package App\Http\Controllers
 */
class CashBoxTypeController extends Controller
{
    /**
     * @group 06. العمليات المالية والخزينة
     * 
     * عرض أنواع الخزن
     * 
     * استرجاع أنواع الصناديق المالية (مثل: خزنة فرعية، حساب بنكي، عهدة موظف).
     * 
     * @queryParam description string البحث بالوصف. Example: بنك
     * @queryParam is_default boolean تصفية حسب الافتراضي. Example: 1
     * 
     * @apiResourceCollection App\Http\Resources\CashBoxType\CashBoxTypeResource
     * @apiResourceModel App\Models\CashBoxType
     */
    public function index(Request $request): JsonResponse
    {
        try {
            /** @var \App\Models\User $authUser */
            $authUser = Auth::user();

            if (!$authUser) {
                return api_unauthorized('يتطلب المصادقة.');
            }

            $cashBoxTypeQuery = CashBoxType::query();

            // تطبيق منطق الصلاحيات العامة
            if ($authUser->hasPermissionTo(perm_key('admin.super'))) {
                // المسؤول العام يرى جميع الأنواع
            } elseif ($authUser->hasAnyPermission([perm_key('cash_box_types.view_all'), perm_key('admin.company')])) {
                // يرى جميع الأنواع (تلقائياً للشركة الحالية عبر السكوب)
            } elseif ($authUser->hasPermissionTo(perm_key('cash_box_types.view_children'))) {
                $cashBoxTypeQuery->whereCreatedByUserOrChildren();
            } elseif ($authUser->hasPermissionTo(perm_key('cash_box_types.view_self'))) {
                $cashBoxTypeQuery->whereCreatedByUser();
            } else {
                return api_forbidden('ليس لديك صلاحية لعرض أنواع الخزن.');
            }

            // التصفية باستخدام الحقول المقدمة
            if (!empty($request->get('description'))) {
                $cashBoxTypeQuery->where('description', 'like', '%' . $request->get('description') . '%');
            }
            if (!empty($request->get('is_default'))) {
                $cashBoxTypeQuery->where('is_default', (bool) $request->get('is_default'));
            }
            if (!empty($request->get('created_at_from'))) {
                $cashBoxTypeQuery->where('created_at', '>=', $request->get('created_at_from') . ' 00:00:00');
            }
            if (!empty($request->get('created_at_to'))) {
                $cashBoxTypeQuery->where('created_at', '<=', $request->get('created_at_to') . ' 23:59:59');
            }

            // تحديد عدد العناصر في الصفحة والفرز

            $perPage = (int) $request->input('per_page', 20); // استخدام 'per_page' كاسم للمدخل
            $sortField = $request->input('sort_by', 'created_at'); // استخدام 'created_at' كقيمة افتراضية للفرز
            $sortOrder = $request->input('sort_order', 'desc');

            $cashBoxTypeQuery->orderBy($sortField, $sortOrder);

            $cashBoxTypes = $perPage == -1
                ? $cashBoxTypeQuery->get()
                : $cashBoxTypeQuery->paginate(max(1, $perPage));


            // التحقق من وجود بيانات وتحديد الرسالة
            return api_success(
                $perPage == -1 ? CashBoxTypeResource::collection($cashBoxTypes) : $cashBoxTypes->setCollection($cashBoxTypes->getCollection()->mapInto(CashBoxTypeResource::class)),
                $cashBoxTypes->isEmpty() ? 'لم يتم العثور على أنواع خزن.' : 'تم استرداد أنواع الخزن بنجاح.'
            );
        } catch (Throwable $e) {
            return api_exception($e, 500);
        }
    }

    /**
     * @group 06. العمليات المالية والخزينة
     * 
     * إضافة نوع خزنة جديد
     * 
     * @bodyParam description string required وصف النوع. Example: نقدي
     * @bodyParam is_default boolean هل هو النوع الافتراضي. Example: false
     */
    public function store(Request $request): JsonResponse
    {
        try {
            /** @var \App\Models\User $authUser */
            $authUser = Auth::user();
            $companyId = $authUser->company_id ?? null;

            if (!$authUser) {
                return api_unauthorized('يتطلب المصادقة.');
            }

            // صلاحيات إنشاء نوع صندوق نقدي
            if (!$authUser->hasPermissionTo(perm_key('admin.super')) && !$authUser->hasPermissionTo(perm_key('cash_box_types.create')) && !$authUser->hasPermissionTo(perm_key('admin.company'))) {
                return api_forbidden('ليس لديك صلاحية لإنشاء أنواع الخزن.');
            }

            DB::beginTransaction();
            try {
                // التحقق من البيانات المدخلة
                $validatedData = $request->validate([
                    'name' => 'required|string|max:255',
                    'description' => 'required|string|max:255',
                    'is_default' => 'boolean',
                    'company_id' => 'nullable|exists:companies,id',
                ]);

                $validatedData['company_id'] = $companyId;
                // تعيين company_id بناءً على صلاحيات المستخدم
                if ($authUser->hasPermissionTo(perm_key('admin.super')) && isset($validatedData['company_id'])) {
                    // السوبر أدمن يمكنه إنشاء نوع لأي شركة يحددها
                    $validatedData['company_id'] = $request->input('company_id');
                }

                $validatedData['created_by'] = $authUser->id;

                $cashBoxType = CashBoxType::create($validatedData);

                DB::commit();
                return api_success(new CashBoxTypeResource($cashBoxType), 'تم إنشاء نوع الخزنة بنجاح.', 201);
            } catch (ValidationException $e) {
                DB::rollback();
                return api_exception($e, 500);
            } catch (Throwable $e) {
                DB::rollback();
                return api_exception($e, 500);
            }
        } catch (Throwable $e) {
            return api_exception($e, 500);
        }
    }

    /**
     * @group 06. العمليات المالية والخزينة
     * 
     * عرض تفاصيل نوع خزنة
     * 
     * @urlParam cashBoxType required معرف النوع. Example: 1
     */
    public function show(CashBoxType $cashBoxType): JsonResponse
    {
        try {
            /** @var \App\Models\User $authUser */
            $authUser = Auth::user();

            if (!$authUser) {
                return api_unauthorized('يتطلب المصادقة.');
            }

            // التحقق من صلاحيات العرض
            $canView = false;
            if ($authUser->hasPermissionTo(perm_key('admin.super'))) {
                $canView = true; // المسؤول العام يرى أي نوع صندوق
            } elseif ($authUser->hasAnyPermission([perm_key('cash_box_types.view_all'), perm_key('admin.company')])) {
                $canView = true; // يرى صناديق شركته (تلقائياً عبر السكوب)
            } elseif ($authUser->hasPermissionTo(perm_key('cash_box_types.view_children'))) {
                $canView = $cashBoxType->createdByUserOrChildren();
            } elseif ($authUser->hasPermissionTo(perm_key('cash_box_types.view_self'))) {
                $canView = $cashBoxType->createdByCurrentUser();
            }

            if ($canView) {
                return api_success(new CashBoxTypeResource($cashBoxType), 'تم استرداد نوع الخزنة بنجاح.');
            }

            return api_forbidden('ليس لديك صلاحية لعرض نوع الخزنة هذا.');
        } catch (Throwable $e) {
            return api_exception($e, 500);
        }
    }

    /**
     * @group 06. العمليات المالية والخزينة
     * 
     * تحديث نوع خزنة
     * 
     * @urlParam cashBoxType required معرف النوع. Example: 1
     * @bodyParam description string وصف النوع المحدث. Example: عهدة شخصية
     */
    public function update(Request $request, CashBoxType $cashBoxType): JsonResponse
    {
        try {
            /** @var \App\Models\User $authUser */
            $authUser = Auth::user();

            if (!$authUser) {
                return api_unauthorized('يتطلب المصادقة.');
            }

            // التحقق من صلاحيات التحديث
            $canUpdate = false;
            if ($authUser->hasPermissionTo(perm_key('admin.super'))) {
                $canUpdate = true; // المسؤول العام يمكنه تعديل أي نوع
            } elseif ($authUser->hasAnyPermission([perm_key('cash_box_types.update_all'), perm_key('admin.company')])) {
                $canUpdate = true; // يمكنه تعديل صناديق شركته (تلقائياً عبر السكوب)
            } elseif ($authUser->hasPermissionTo(perm_key('cash_box_types.update_children'))) {
                $canUpdate = $cashBoxType->createdByUserOrChildren();
            } elseif ($authUser->hasPermissionTo(perm_key('cash_box_types.update_self'))) {
                $canUpdate = $cashBoxType->createdByCurrentUser();
            }

            if (!$canUpdate) {
                return api_forbidden('ليس لديك صلاحية لتحديث نوع الخزنة هذا.');
            }

            DB::beginTransaction();
            try {
                $validatedData = $request->validate([
                    'name' => 'required|string|max:255',
                    'description' => 'required|string|max:255',
                    'is_default' => 'boolean',
                    'company_id' => 'nullable|exists:companies,id',
                ]);

                // التأكد من أن المستخدم مصرح له بتغيير company_id إذا كان سوبر أدمن
                if (isset($validatedData['company_id']) && $validatedData['company_id'] != $cashBoxType->company_id && !$authUser->hasPermissionTo(perm_key('admin.super'))) {
                    return api_forbidden('لا يمكنك تغيير شركة نوع الخزنة إلا إذا كنت مدير عام.');
                }
                // إذا لم يتم تحديد company_id في الطلب ولكن المستخدم سوبر أدمن، لا تغير company_id الخاصة بالصندوق الحالي
                if (!$authUser->hasPermissionTo(perm_key('admin.super')) || !isset($validatedData['company_id'])) {
                    unset($validatedData['company_id']);
                }

                $cashBoxType->update($validatedData);

                DB::commit();
                return api_success(new CashBoxTypeResource($cashBoxType), 'تم تحديث نوع الخزنة بنجاح.');
            } catch (ValidationException $e) {
                DB::rollback();
                return api_error('فشل التحقق من صحة البيانات أثناء تحديث نوع الخزنة.', $e->errors(), 422);
            } catch (Throwable $e) {
                DB::rollback();
                return api_error('حدث خطأ أثناء تحديث نوع الخزنة.', [], 500);
            }
        } catch (Throwable $e) {
            return api_exception($e, 500);
        }
    }

    /**
     * @group 06. العمليات المالية والخزينة
     * 
     * حذف أنواع خزن (Batch Delete)
     * 
     * @bodyParam item_ids integer[] required مصفوفة المعرفات. Example: [2, 3]
     */
    public function destroy(Request $request): JsonResponse
    {
        try {
            /** @var \App\Models\User $authUser */
            $authUser = Auth::user();

            if (!$authUser) {
                return api_unauthorized('يتطلب المصادقة.');
            }

            $cashBoxTypeIds = $request->input('item_ids');

            if (!$cashBoxTypeIds || !is_array($cashBoxTypeIds)) {
                return api_error('تم توفير معرفات أنواع الخزن غير صالحة.', [], 400);
            }

            $cashBoxTypesToDelete = CashBoxType::whereIn('id', $cashBoxTypeIds)->get();

            DB::beginTransaction();
            try {
                $deletedTypes = [];
                foreach ($cashBoxTypesToDelete as $cashBoxType) {
                    $canDelete = false;

                    // تطبيق منطق الصلاحيات
                    if ($authUser->hasPermissionTo(perm_key('admin.super'))) {
                        $canDelete = true;
                    } elseif ($authUser->hasAnyPermission([perm_key('cash_box_types.delete_all'), perm_key('admin.company')])) {
                        $canDelete = true;
                    } elseif ($authUser->hasPermissionTo(perm_key('cash_box_types.delete_children'))) {
                        $canDelete = $cashBoxType->createdByUserOrChildren();
                    } elseif ($authUser->hasPermissionTo(perm_key('cash_box_types.delete_self'))) {
                        $canDelete = $cashBoxType->createdByCurrentUser();
                    }

                    if (!$canDelete) {
                        DB::rollBack();
                        return api_forbidden("ليس لديك إذن لحذف نوع الخزنة '{$cashBoxType->name}'.");
                    }

                    // ✅ حماية من حذف أنواع الصناديق الأساسية (is_system)
                    if ($cashBoxType->is_system) {
                        DB::rollBack();
                        return api_error(
                            "لا يمكن حذف نوع صندوق أساسي من النظام: '{$cashBoxType->name}'. يمكنك تعطيله بدلاً من ذلك.",
                            [
                                'suggestion' => 'يمكنك تعطيل النوع بتغيير حالة is_active إلى false',
                                'is_system' => true,
                                'type_name' => $cashBoxType->name
                            ],
                            403
                        );
                    }

                    // تحقق من وجود صناديق مرتبطة
                    if ($cashBoxType->cashBoxes()->exists()) {
                        DB::rollBack();
                        return api_error("لا يمكن حذف نوع الخزنة '{$cashBoxType->name}' لأنه مستخدم في صناديق موجودة.", [], 422);
                    }

                    // حفظ نسخة من العنصر قبل حذفه لإرجاعه في الاستجابة
                    $deletedType = $cashBoxType->replicate();
                    $deletedType->setRelations($cashBoxType->getRelations()); // نسخ العلاقات المحملة

                    $cashBoxType->delete();
                    $deletedTypes[] = $deletedType;
                }

                DB::commit();
                return api_success($deletedTypes, 'تم حذف أنواع الخزن بنجاح.');
            } catch (Throwable $e) {
                DB::rollback();
                return api_error('حدث خطأ أثناء حذف أنواع الخزن.', [], 500);
            }
        } catch (Throwable $e) {
            return api_exception($e, 500);
        }
    }

    /**
     * @group 06. العمليات المالية والخزينة
     * 
     * تفعيل/تعطيل نوع الخزنة
     * 
     * @urlParam id required معرف النوع. Example: 1
     */
    public function toggle(string $id): JsonResponse
    {
        try {
            /** @var \App\Models\User $authUser */
            $authUser = Auth::user();

            if (!$authUser) {
                return api_unauthorized('يتطلب المصادقة.');
            }

            $cashBoxType = CashBoxType::findOrFail($id);

            // التحقق من الصلاحيات (نفس منطق update)
            $canUpdate = false;
            if ($authUser->hasPermissionTo(perm_key('admin.super'))) {
                $canUpdate = true;
            } elseif ($authUser->hasAnyPermission([perm_key('cash_box_types.update_all'), perm_key('admin.company')])) {
                $canUpdate = true;
            } elseif ($authUser->hasPermissionTo(perm_key('cash_box_types.update_children'))) {
                $canUpdate = $cashBoxType->createdByUserOrChildren();
            } elseif ($authUser->hasPermissionTo(perm_key('cash_box_types.update_self'))) {
                $canUpdate = $cashBoxType->createdByCurrentUser();
            }

            if (!$canUpdate) {
                return api_forbidden('ليس لديك إذن لتعديل حالة نوع الصندوق هذا.');
            }

            // تبديل الحالة
            $cashBoxType->is_active = !$cashBoxType->is_active;
            $cashBoxType->save();

            $status = $cashBoxType->is_active ? 'مفعّل' : 'معطّل';
            return api_success(
                new CashBoxTypeResource($cashBoxType),
                "نوع الصندوق '{$cashBoxType->name}' الآن {$status}."
            );
        } catch (Throwable $e) {
            return api_exception($e, 500);
        }
    }
}
