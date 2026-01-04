<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Http\Requests\Payment\StorePaymentRequest;
use App\Http\Requests\Payment\UpdatePaymentRequest;
use App\Http\Resources\Payment\PaymentResource;
use App\Models\Payment;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Throwable;

class PaymentController extends Controller
{
    private array $relations;

    public function __construct()
    {
        $this->relations = [
            'user',
            'installments',
            'cashBox',
            'paymentMethod',
            'creator', // للمصادقة على createdByUser/OrChildren
            'company', // للتحقق من belongsToCurrentCompany
        ];
    }

    /**
     * @group 06. العمليات المالية والخزينة
     * 
     * عرض قائمة المدفوعات
     * 
     * @queryParam user_id integer فلترة حسب المستخدم. Example: 1
     * @queryParam payment_method_id integer فلترة حسب طريقة الدفع. Example: 1
     * @queryParam cash_box_id integer فلترة حسب الخزنة. Example: 1
     * @queryParam amount_from number المبلغ من. Example: 100
     * @queryParam amount_to number المبلغ إلى. Example: 1000
     * @queryParam paid_at_from date تاريخ الدفع من. Example: 2023-01-01
     * @queryParam per_page integer عدد النتائج. Default: 20
     * 
     * @apiResourceCollection App\Http\Resources\Payment\PaymentResource
     * @apiResourceModel App\Models\Payment
     */
    public function index(Request $request): JsonResponse
    {
        try {
            /** @var \App\Models\User $authUser */
            $authUser = Auth::user();

            if (!$authUser) {
                return api_unauthorized('يتطلب المصادقة.');
            }

            $query = Payment::query()->with($this->relations);
            $companyId = $authUser->company_id ?? null;

            // تطبيق فلترة الصلاحيات بناءً على صلاحيات العرض
            if ($authUser->hasPermissionTo(perm_key('admin.super'))) {
                // المسؤول العام يرى جميع المدفوعات
            } elseif ($authUser->hasAnyPermission([perm_key('payments.view_all'), perm_key('admin.company')])) {
                // يرى جميع المدفوعات الخاصة بالشركة النشطة
                $query->whereCompanyIsCurrent();
            } elseif ($authUser->hasPermissionTo(perm_key('payments.view_children'))) {
                // يرى المدفوعات التي أنشأها المستخدم أو المستخدمون التابعون له، ضمن الشركة النشطة
                $query->whereCompanyIsCurrent()->whereCreatedByUserOrChildren();
            } elseif ($authUser->hasPermissionTo(perm_key('payments.view_self'))) {
                // يرى المدفوعات التي أنشأها المستخدم فقط، ومرتبطة بالشركة النشطة
                $query->whereCompanyIsCurrent()->whereCreatedByUser();
            } else {
                return api_forbidden('ليس لديك إذن لعرض المدفوعات.');
            }

            // فلاتر الطلب الإضافية
            if ($request->filled('user_id')) {
                $query->where('user_id', $request->input('user_id'));
            }
            if ($request->filled('payment_method_id')) {
                $query->where('payment_method_id', $request->input('payment_method_id'));
            }
            if ($request->filled('cash_box_id')) {
                $query->where('cash_box_id', $request->input('cash_box_id'));
            }
            if ($request->filled('amount_from')) {
                $query->where('amount', '>=', $request->input('amount_from'));
            }
            if ($request->filled('amount_to')) {
                $query->where('amount', '<=', $request->input('amount_to'));
            }
            if ($request->filled('paid_at_from')) {
                $query->where('payment_date', '>=', $request->input('paid_at_from'));
            }
            if ($request->filled('paid_at_to')) {
                $query->where('payment_date', '<=', $request->input('paid_at_to'));
            }

            // تحديد عدد العناصر في الصفحة والفرز
            $perPage = max(1, (int) $request->input('per_page', 20));
            $sortField = $request->input('sort_by', 'payment_date');
            $sortOrder = $request->input('sort_order', 'desc');

            $payments = $query->orderBy($sortField, $sortOrder)->paginate($perPage);

            if ($payments->isEmpty()) {
                return api_success([], 'لم يتم العثور على مدفوعات.');
            } else {
                return api_success(PaymentResource::collection($payments), 'تم جلب المدفوعات بنجاح.');
            }
        } catch (Throwable $e) {
            return api_exception($e);
        }
    }

    /**
     * @group 06. العمليات المالية والخزينة
     * 
     * تسجيل دفعة جديدة
     * 
     * @bodyParam user_id integer required معرف العميل/المورد. Example: 1
     * @bodyParam amount number required المبلغ المالي. Example: 1500.75
     * @bodyParam payment_method_id integer required معرف طريقة الدفع. Example: 1
     * @bodyParam cash_box_id integer required معرف الخزنة المستلمة/الصادرة. Example: 1
     * @bodyParam paid_at datetime تاريخ وتوقيت الدفع. Example: 2023-05-20 14:30:00
     * @bodyParam notes string ملاحظات إضافية. Example: دفعة مقدمة من الحساب
     */
    public function store(StorePaymentRequest $request): JsonResponse
    {
        try {
            /** @var \App\Models\User $authUser */
            $authUser = Auth::user();
            $companyId = $authUser->company_id ?? null;

            if (!$authUser || !$companyId) {
                return api_unauthorized('يتطلب المصادقة أو الارتباط بالشركة.');
            }

            if (!$authUser->hasPermissionTo(perm_key('admin.super')) && !$authUser->hasPermissionTo(perm_key('payments.create')) && !$authUser->hasPermissionTo(perm_key('admin.company'))) {
                return api_forbidden('ليس لديك إذن لإنشاء مدفوعات.');
            }

            DB::beginTransaction();
            try {
                $validatedData = $request->validated();
                $validatedData['created_by'] = $authUser->id;
                $validatedData['company_id'] = $companyId;

                // التحقق من أن صندوق النقد وطريقة الدفع ينتميان لنفس الشركة
                $cashBox = \App\Models\CashBox::where('id', $validatedData['cash_box_id'])
                    ->where('company_id', $companyId)
                    ->firstOrFail();
                $paymentMethod = \App\Models\PaymentMethod::where('id', $validatedData['payment_method_id'])
                    ->where('company_id', $companyId)
                    ->firstOrFail();

                $payment = Payment::create($validatedData);
                $payment->load($this->relations);
                DB::commit();
                return api_success(new PaymentResource($payment), 'تم إنشاء الدفعة بنجاح.', 201);
            } catch (ValidationException $e) {
                DB::rollBack();
                return api_error('فشل التحقق من صحة البيانات أثناء تخزين الدفعة.', $e->errors(), 422);
            } catch (Throwable $e) {
                DB::rollBack();
                return api_error('حدث خطأ أثناء حفظ الدفعة.', [], 500);
            }
        } catch (Throwable $e) {
            return api_exception($e);
        }
    }

    /**
     * @group 06. العمليات المالية والخزينة
     * 
     * عرض دفعة محددة
     * 
     * @urlParam id required معرف الدفعة. Example: 1
     */
    public function show($id): JsonResponse
    {
        try {
            /** @var \App\Models\User $authUser */
            $authUser = Auth::user();
            $companyId = $authUser ? $authUser->company_id : null;

            if (!$authUser || !$companyId) {
                return api_unauthorized('يتطلب المصادقة أو الارتباط بالشركة.');
            }

            $payment = Payment::with($this->relations)->findOrFail($id);

            $canView = false;
            if ($authUser->hasPermissionTo(perm_key('admin.super'))) {
                $canView = true;
            } elseif ($authUser->hasAnyPermission([perm_key('payments.view_all'), perm_key('admin.company')])) {
                $canView = $payment->belongsToCurrentCompany();
            } elseif ($authUser->hasPermissionTo(perm_key('payments.view_children'))) {
                $canView = $payment->belongsToCurrentCompany() && $payment->createdByUserOrChildren();
            } elseif ($authUser->hasPermissionTo(perm_key('payments.view_self'))) {
                $canView = $payment->belongsToCurrentCompany() && $payment->createdByCurrentUser();
            }

            if ($canView) {
                return api_success(new PaymentResource($payment), 'تم استرداد الدفعة بنجاح.');
            }

            return api_forbidden('ليس لديك إذن لعرض هذه الدفعة.');
        } catch (Throwable $e) {
            return api_exception($e);
        }
    }

    /**
     * @group 06. العمليات المالية والخزينة
     * 
     * تحديث دفعة
     * 
     * @urlParam id required معرف الدفعة. Example: 1
     * @bodyParam amount number المبلغ الجديد. Example: 2000
     */
    public function update(UpdatePaymentRequest $request, string $id): JsonResponse
    {
        try {
            /** @var \App\Models\User $authUser */
            $authUser = Auth::user();
            $companyId = $authUser->company_id ?? null;

            if (!$authUser || !$companyId) {
                return api_unauthorized('يتطلب المصادقة أو الارتباط بالشركة.');
            }

            $payment = Payment::with(['company', 'creator'])->findOrFail($id);

            $canUpdate = false;
            if ($authUser->hasPermissionTo(perm_key('admin.super'))) {
                $canUpdate = true;
            } elseif ($authUser->hasAnyPermission([perm_key('payments.update_all'), perm_key('admin.company')])) {
                $canUpdate = $payment->belongsToCurrentCompany();
            } elseif ($authUser->hasPermissionTo(perm_key('payments.update_children'))) {
                $canUpdate = $payment->belongsToCurrentCompany() && $payment->createdByUserOrChildren();
            } elseif ($authUser->hasPermissionTo(perm_key('payments.update_self'))) {
                $canUpdate = $payment->belongsToCurrentCompany() && $payment->createdByCurrentUser();
            }

            if (!$canUpdate) {
                return api_forbidden('ليس لديك إذن لتحديث هذه الدفعة.');
            }

            DB::beginTransaction();
            try {
                $validatedData = $request->validated();
                $validatedData['updated_by'] = $authUser->id;

                // التحقق من أن صندوق النقد وطريقة الدفع ينتميان لنفس الشركة إذا تم تغييرها
                if (isset($validatedData['cash_box_id']) && $validatedData['cash_box_id'] != $payment->cash_box_id) {
                    $cashBox = \App\Models\CashBox::where('id', $validatedData['cash_box_id'])
                        ->where('company_id', $companyId)
                        ->firstOrFail();
                }
                if (isset($validatedData['payment_method_id']) && $validatedData['payment_method_id'] != $payment->payment_method_id) {
                    $paymentMethod = \App\Models\PaymentMethod::where('id', $validatedData['payment_method_id'])
                        ->where('company_id', $companyId)
                        ->firstOrFail();
                }

                $payment->update($validatedData);
                $payment->load($this->relations);
                DB::commit();
                return api_success(new PaymentResource($payment), 'تم تحديث الدفعة بنجاح.');
            } catch (ValidationException $e) {
                DB::rollBack();
                return api_error('فشل التحقق من صحة البيانات أثناء تحديث الدفعة.', $e->errors(), 422);
            } catch (Throwable $e) {
                DB::rollBack();
                return api_error('حدث خطأ أثناء تحديث الدفعة.', [], 500);
            }
        } catch (Throwable $e) {
            return api_exception($e);
        }
    }

    /**
     * @group 06. العمليات المالية والخزينة
     * 
     * حذف دفعة
     * 
     * @urlParam id required معرف الدفعة. Example: 1
     */
    public function destroy(string $id): JsonResponse
    {
        try {
            /** @var \App\Models\User $authUser */
            $authUser = Auth::user();
            $companyId = $authUser->company_id ?? null;

            if (!$authUser || !$companyId) {
                return api_unauthorized('يتطلب المصادقة أو الارتباط بالشركة.');
            }

            $payment = Payment::with(['company', 'creator', 'installments'])->findOrFail($id);

            $canDelete = false;
            if ($authUser->hasPermissionTo(perm_key('admin.super'))) {
                $canDelete = true;
            } elseif ($authUser->hasAnyPermission([perm_key('payments.delete_all'), perm_key('admin.company')])) {
                $canDelete = $payment->belongsToCurrentCompany();
            } elseif ($authUser->hasPermissionTo(perm_key('payments.delete_children'))) {
                $canDelete = $payment->belongsToCurrentCompany() && $payment->createdByUserOrChildren();
            } elseif ($authUser->hasPermissionTo(perm_key('payments.delete_self'))) {
                $canDelete = $payment->belongsToCurrentCompany() && $payment->createdByCurrentUser();
            }

            if (!$canDelete) {
                return api_forbidden('ليس لديك إذن لحذف هذه الدفعة.');
            }

            DB::beginTransaction();
            try {
                // تحقق مما إذا كانت الدفعة مرتبطة بأي أقساط
                if ($payment->installments()->exists()) {
                    DB::rollBack();
                    return api_error('لا يمكن حذف الدفعة. إنها مرتبطة بأقساط موجودة.', [], 409);
                }

                $deletedPayment = $payment->replicate();
                $deletedPayment->setRelations($payment->getRelations());

                $payment->delete();
                DB::commit();
                return api_success(new PaymentResource($deletedPayment), 'تم حذف الدفعة بنجاح.');
            } catch (Throwable $e) {
                DB::rollBack();
                return api_exception($e);
            }
        } catch (Throwable $e) {
            return api_exception($e);
        }
    }
}
