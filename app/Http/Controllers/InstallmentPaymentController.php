<?php

namespace App\Http\Controllers;

use App\Models\InstallmentPayment;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use App\Services\InstallmentPaymentService;
use Illuminate\Validation\ValidationException;
use App\Http\Requests\InstallmentPayment\PayInstallmentsRequest;
use App\Http\Resources\Installment\InstallmentResource;
use App\Http\Resources\InstallmentPayment\InstallmentPaymentResource;
use Throwable;

class InstallmentPaymentController extends Controller
{
    private array $relations;

    public function __construct()
    {
        $this->relations = [
            'plan.customer',
            'plan.invoice.customer',
            'plan.invoice.company',
            'cashBox',
            'creator',
            'details.installment',
        ];
    }

    /**
     * عرض قائمة بدفعات الأقساط.
     */
    public function index(Request $request): JsonResponse
    {
        try {
            /** @var \App\Models\User $authUser */
            $authUser = Auth::user();
            $query = InstallmentPayment::with($this->relations);

            if (!$authUser) {
                return api_unauthorized('يتطلب المصادقة.');
            }

            // تطبيق فلترة الصلاحيات بناءً على صلاحيات العرض
            if ($authUser->hasPermissionTo(perm_key('admin.super'))) {
                // المسؤول العام يرى جميع الدفعات
            } elseif ($authUser->hasAnyPermission([perm_key('payments.view_all'), perm_key('admin.company')])) {
                // يرى جميع الدفعات الخاصة بالشركة النشطة
                $query->whereCompanyIsCurrent();
            } elseif ($authUser->hasPermissionTo(perm_key('payments.view_children'))) {
                // يرى الدفعات التي أنشأها المستخدم أو المستخدمون التابعون له
                $query->whereCompanyIsCurrent()->whereCreatedByUserOrChildren();
            } elseif ($authUser->hasPermissionTo(perm_key('payments.view_self'))) {
                // يرى الدفعات التي أنشأها المستخدم فقط
                $query->whereCompanyIsCurrent()->whereCreatedByUser();
            } else {
                return api_forbidden('ليس لديك إذن لعرض سجلات الدفع.');
            }

            // التصفية
            if ($request->filled('search')) {
                $search = trim($request->input('search'));
                $query->smartSearch($search, ['reference_number'], [
                    'plan.customer' => ['full_name', 'nickname', 'phone'],
                    'plan.invoice' => ['invoice_number']
                ]);
            }

            if ($request->filled('installment_plan_id')) {
                $query->where('installment_plan_id', $request->input('installment_plan_id'));
            }
            if ($request->filled('date_from')) {
                $query->where('payment_date', '>=', $request->input('date_from'));
            }
            if ($request->filled('date_to')) {
                $query->where('payment_date', '<=', $request->input('date_to'));
            }

            $perPage = max(1, (int) $request->get('limit', 20));
            $sortBy = $request->get('sort_by', 'payment_date');
            $sortOrder = $request->get('sort_order', 'desc');
            $payments = $query->orderBy($sortBy, $sortOrder)->paginate($perPage);

            // تحسين النتائج بالتشابه (Similarity Refinement)
            if ($request->filled('search') && $payments->isNotEmpty()) {
                $search = $request->input('search');
                $fieldsToCompare = ['reference_number', 'plan.customer.full_name', 'plan.customer.nickname', 'plan.customer.phone', 'plan.invoice.invoice_number'];

                $refined = (new InstallmentPayment())->refineSimilarity(collect($payments->items()), $search, $fieldsToCompare, 80);
                /** @var \Illuminate\Pagination\LengthAwarePaginator $payments */
                $payments->setCollection($refined);
            }

            if ($payments->isEmpty()) {
                // If after refinement, the collection is empty, you might want to return an empty success or a specific message.
                // For now, it will proceed to the general success message with an empty collection.
            }
            return api_success(InstallmentPaymentResource::collection($payments), 'تم جلب سجلات الدفع بنجاح.');
        } catch (Throwable $e) {
            return api_exception($e, 500);
        }
    }

    /**
     * Display the specified payment.
     */
    public function show(string $id): JsonResponse
    {
        try {
            $authUser = Auth::user();
            $payment = InstallmentPayment::with($this->relations)->findOrFail($id);

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
                return api_success(new InstallmentPaymentResource($payment), 'تم استرداد بيانات الدفعة بنجاح.');
            }

            return api_forbidden('ليس لديك إذن لعرض هذه الدفعة.');
        } catch (Throwable $e) {
            return api_exception($e, 500);
        }
    }

    /**
     * Update the specified payment (Note: Payments are usually read-only for history).
     */
    public function update(Request $request, string $id): JsonResponse
    {
        return api_forbidden('لا يمكن تعديل سجلات الدفع بعد تأكيدها.');
    }

    /**
     * Remove the specified payment (Note: Payments are usually read-only for history).
     */
    public function destroy(string $id): JsonResponse
    {
        return api_forbidden('لا يمكن حذف سجلات الدفع لضمان نزاهة البيانات المالية.');
    }

    /**
     * @group 04. نظام الأقساط
     * 
     * تحصيل أقساط (دفع)
     * 
     * عملية سداد مبلغ للأقساط المحددة، يتم توزيع المبلغ تلقائياً على الأقساط المختارة.
     * 
     * @bodyParam installment_ids array required معرفات الأقساط المراد دفعها. Example: [1, 2]
     * @bodyParam amount number required المبلغ المدفوع إجمالاً. Example: 1000
     * @bodyParam payment_method_id integer required معرف طريقة الدفع. Example: 1
     * @bodyParam cash_box_id integer معرف الخزنة (اختياري، يستخدم الافتراضي إذا لم يحدد). Example: 1
     * @bodyParam paid_at datetime تاريخ الدفع. Example: 2023-10-25 14:00:00
     */
    public function payInstallments(PayInstallmentsRequest $request): JsonResponse
    {
        try {
            /** @var \App\Models\User $authUser */
            $authUser = Auth::user();
            $companyId = $authUser->company_id ?? null;
            $validatedData = $request->validated();

            $cashBoxId = $validatedData['cash_box_id'] ?? $authUser->getDefaultCashBoxForCompany()?->id;
            if (!$cashBoxId) {
                return api_error('لم يتم العثور على صندوق نقدي افتراضي للمستخدم.', [], 400);
            }

            // التحقق من أن الصندوق النقدي المختار ينتمي لشركة المستخدم
            $cashBox = \App\Models\CashBox::find($cashBoxId);
            if (
                !$cashBox ||
                (!$authUser->hasPermissionTo(perm_key('admin.super')) && $cashBox->company_id !== $companyId)
            ) {
                return api_forbidden('صندوق النقد المحدد غير صالح أو غير متاح لشركتك.');
            }

            DB::beginTransaction();
            try {
                $service = new InstallmentPaymentService();
                $result = $service->payInstallments(
                    $validatedData['installment_ids'],
                    $validatedData['amount'],
                    [
                        'user_id' => $validatedData['user_id'] ?? null,
                        'installment_plan_id' => $validatedData['installment_plan_id'],
                        'payment_method_id' => $validatedData['payment_method_id'],
                        'cash_box_id' => $cashBoxId,
                        'notes' => $validatedData['notes'] ?? '',
                        'paid_at' => $validatedData['paid_at'] ?? $validatedData['payment_date'] ?? now(),
                        'reference_number' => $validatedData['reference_number'] ?? null,
                        'amount' => $validatedData['amount'],
                    ]
                );

                // يمكنك الآن الوصول إلى سجل الدفعة والأقساط المتأثرة
                $installmentPayment = $result['installmentPayment'];
                $affectedInstallments = $result['installments'];

                DB::commit();

                // يمكنك اختيار ما تريد إرجاعه في الرد JSON.
                // على سبيل المثال، يمكنك إرجاع سجل الدفعة الرئيسي والأقساط المتأثرة:
                $installmentPayment->load($this->relations);

                return api_success([
                    'payment_record' => new InstallmentPaymentResource($installmentPayment),
                    'paid_installments' => InstallmentResource::collection($affectedInstallments),
                    'excess_amount' => (float) $result['excess_amount'],
                    'next_installment' => $result['next_installment'] ? new InstallmentResource($result['next_installment']) : null,
                ], 'تم دفع الأقساط بنجاح.');
            } catch (ValidationException $e) {
                DB::rollBack();
                return api_error('فشل التحقق من صحة البيانات أثناء دفع الأقساط.', $e->errors(), 422);
            } catch (Throwable $e) {
                DB::rollBack();
                return api_error(
                    'حدث خطأ أثناء دفع الأقساط.',
                    [
                        'message' => $e->getMessage(),
                        'file' => $e->getFile(),
                        'line' => $e->getLine(),
                        'trace' => config('app.debug') ? collect($e->getTrace())->take(5) : [],
                    ],
                    500
                );
            }
        } catch (Throwable $e) {
            return api_exception($e, 500);
        }
    }

    /**
     * @group 04. نظام الأقساط
     * 
     * إيداع المبلغ الزائد في رصيد العميل
     */
    public function depositExcess(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'installment_plan_id' => 'required|exists:installment_plans,id',
            'amount' => 'required|numeric|min:0.01',
            'notes' => 'nullable|string',
        ]);

        try {
            DB::beginTransaction();
            $plan = \App\Models\InstallmentPlan::findOrFail($validated['installment_plan_id']);
            $customer = $plan->user; // العميل المرتبط بالخطة

            if (!$customer) {
                return api_error('لم يتم العثور على العميل المرتبط بهذه الخطة.');
            }

            // إيداع في رصيد العميل (تقليل مديونية أو رصيد دائن)
            $customer->deposit($validated['amount'], null, $validated['notes'] ?? 'إيداع فائض تحصيل أقساط');

            DB::commit();
            return api_success([], 'تم إضافة المبلغ إلى رصيد العميل بنجاح.');
        } catch (Throwable $e) {
            DB::rollBack();
            return api_exception($e);
        }
    }
}
