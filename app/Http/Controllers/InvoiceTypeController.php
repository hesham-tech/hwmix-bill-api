<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Http\Requests\InvoiceType\StoreInvoiceTypeRequest;
use App\Http\Requests\InvoiceType\UpdateInvoiceTypeRequest;
use App\Http\Resources\InvoiceType\InvoiceTypeResource;
use App\Models\InvoiceType;
use App\Models\Invoice; // لاستخدامه في التحقق من العلاقات قبل الحذف
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Throwable;

class InvoiceTypeController extends Controller
{
    private array $relations;

    public function __construct()
    {
        $this->relations = [
            'invoices',  // العلاقة مع الفواتير المرتبطة بهذا النوع
            'company',   // للتحقق من belongsToCurrentCompany
            'creator',   // للتحقق من createdByCurrentUser/OrChildren
        ];
    }

    /**
     * @group 08. إعدادات النظام وتفضيلاته
     * 
     * عرض أنواع المستندات
     * 
     * استرجاع أنواع المعاملات المالية المتاحة (فواتير مبيعات، مشتريات، مرتجعات، سندات قبص).
     * 
     * @queryParam context string سياق النوع (sale, purchase).
     * 
     * @apiResourceCollection App\Http\Resources\InvoiceType\InvoiceTypeResource
     * @apiResourceModel App\Models\InvoiceType
     */
    public function index(Request $request): JsonResponse
    {
        try {
            /** @var \App\Models\User $authUser */
            $authUser = Auth::user();
            $companyId = $authUser->company_id ?? null;

            if (!$authUser || !$companyId) {
                return api_unauthorized('يتطلب المصادقة أو الارتباط بالشركة.');
            }

            // جلب الأنواع المرتبطة بالشركة الحالية من جدول الربط
            $company = \App\Models\Company::find($companyId);

            $types = $company->invoiceTypes()
                ->when($request->filled('context'), function ($q) use ($request) {
                    $q->where('context', $request->input('context'));
                })
                ->get();

            // إضافة is_active من الـ pivot إلى كل نوع (تحويل لـ boolean)
            $types = $types->map(function ($type) {
                $type->is_active = (bool) $type->pivot->is_active;
                unset($type->pivot); // إزالة البيانات الزائدة
                return $type;
            });

            if ($types->isEmpty()) {
                return api_success([], 'لم يتم العثور على أنواع فواتير.');
            }

            return api_success(InvoiceTypeResource::collection($types), 'تم جلب أنواع الفواتير بنجاح.');
        } catch (Throwable $e) {
            return api_exception($e);
        }
    }

    /**
     * @group 08. إعدادات النظام وتفضيلاته
     * 
     * إضافة نوع مستند جديد
     * 
     * @bodyParam name string required الاسم بالعربية. Example: فاتورة مبيعات ضريبية
     * @bodyParam code string required كود فريد للنوع. Example: SALE_TAX
     * @bodyParam context string required السياق (sale/purchase). Example: sale
     */
    public function store(StoreInvoiceTypeRequest $request): JsonResponse
    {
        return api_error(
            'لا يمكن إنشاء أنواع فواتير جديدة. أنواع الفواتير محمية ومُعرّفة مسبقاً في النظام.',
            [],
            403
        );
    }

    /**
     * عرض نوع فاتورة محدد.
     *
     * @param string $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function show(string $id): JsonResponse
    {
        try {
            /** @var \App\Models\User $authUser */
            $authUser = Auth::user();
            $companyId = $authUser->company_id ?? null;

            if (!$authUser || !$companyId) {
                return api_unauthorized('يتطلب المصادقة أو الارتباط بالشركة.');
            }

            $type = InvoiceType::with($this->relations)->findOrFail($id);

            $canView = false;
            if ($authUser->hasPermissionTo(perm_key('admin.super'))) {
                $canView = true;
            } elseif ($authUser->hasAnyPermission([perm_key('invoice_types.view_all'), perm_key('admin.company')])) {
                $canView = $type->belongsToCurrentCompany();
            } elseif ($authUser->hasPermissionTo(perm_key('invoice_types.view_children'))) {
                $canView = $type->belongsToCurrentCompany() && $type->createdByUserOrChildren();
            } elseif ($authUser->hasPermissionTo(perm_key('invoice_types.view_self'))) {
                $canView = $type->belongsToCurrentCompany() && $type->createdByCurrentUser();
            }

            if ($canView) {
                return api_success(new InvoiceTypeResource($type), 'تم استرداد نوع الفاتورة بنجاح.');
            }

            return api_forbidden('ليس لديك إذن لعرض نوع الفاتورة هذا.');
        } catch (Throwable $e) {
            return api_exception($e);
        }
    }

    /**
     * تحديث نوع فاتورة محدد.
     *
     * @param UpdateInvoiceTypeRequest $request
     * @param string $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(UpdateInvoiceTypeRequest $request, string $id): JsonResponse
    {
        try {
            /** @var \App\Models\User $authUser */
            $authUser = Auth::user();
            $companyId = $authUser->company_id ?? null;

            if (!$authUser || !$companyId) {
                return api_unauthorized('يتطلب المصادقة أو الارتباط بالشركة.');
            }

            // التحقق من وجود النوع
            $type = InvoiceType::findOrFail($id);
            $company = \App\Models\Company::find($companyId);

            // التحقق من أن الشركة مرتبطة بهذا النوع
            if (!$company->invoiceTypes()->where('invoice_type_id', $id)->exists()) {
                return api_error('هذا النوع غير مرتبط بشركتك.', [], 404);
            }

            DB::beginTransaction();
            try {
                $validatedData = $request->validated();

                // السماح فقط بتغيير is_active
                if (!isset($validatedData['is_active'])) {
                    DB::rollBack();
                    return api_error(
                        'يمكن فقط تفعيل أو تعطيل أنواع الفواتير.',
                        [],
                        403
                    );
                }

                // تحديث في جدول الربط (pivot) فقط
                $company->invoiceTypes()->updateExistingPivot($id, [
                    'is_active' => $validatedData['is_active'],
                ]);

                DB::commit();

                // إعادة جلب النوع مع pivot محدث
                $updatedType = $company->invoiceTypes()->find($id);
                $updatedType->is_active = (bool) $updatedType->pivot->is_active;
                unset($updatedType->pivot);

                $statusMessage = $validatedData['is_active']
                    ? 'تم تفعيل نوع الفاتورة بنجاح.'
                    : 'تم تعطيل نوع الفاتورة بنجاح.';

                return api_success(new InvoiceTypeResource($updatedType), $statusMessage);
            } catch (ValidationException $e) {
                DB::rollBack();
                return api_error('فشل التحقق من صحة البيانات.', $e->errors(), 422);
            } catch (Throwable $e) {
                DB::rollBack();
                return api_exception($e);
            }
        } catch (Throwable $e) {
            return api_exception($e);
        }
    }

    /**
     * حذف نوع فاتورة محدد.
     *
     * @param string $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy(string $id): JsonResponse
    {
        return api_error(
            'لا يمكن حذف أنواع الفواتير. يمكنك فقط تعطيلها باستخدام خاصية التفعيل/التعطيل.',
            [],
            403
        );
    }
}
