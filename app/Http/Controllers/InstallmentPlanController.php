<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Http\Requests\InstallmentPlan\StoreInstallmentPlanRequest;
use App\Http\Requests\InstallmentPlan\UpdateInstallmentPlanRequest;
use App\Http\Resources\InstallmentPlan\InstallmentPlanResource;
use App\Models\InstallmentPlan;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Throwable;

class InstallmentPlanController extends Controller
{
    private array $relations;

    public function __construct()
    {
        $this->relations = [
            'customer',       // المستخدم الذي يخصه خطة التقسيط
            'creator',    // المستخدم الذي أنشأ خطة التقسيط
            'invoice.items.variant',
            'installments',
            'company',    // يجب تحميل الشركة للتحقق من belongsToCurrentCompany
        ];
    }

    /**
     * @group 04. نظام الأقساط
     * 
     * عرض قائمة خطط التقسيط
     * 
     * @queryParam status string الحالة (active, completed, canceled). Example: active
     * @queryParam search string البحث بالاسم أو الهاتف. Example: محمد
     * @queryParam per_page integer عدد النتائج. Default: 20
     */
    public function index(Request $request): JsonResponse
    {
        try {
            /** @var \App\Models\User $authUser */
            $authUser = Auth::user();

            if (!$authUser) {
                return api_unauthorized('يتطلب المصادقة.');
            }

            $query = InstallmentPlan::with($this->relations);

            // 🔒 تطبيق فلترة الصلاحيات بناءً على صلاحيات العرض
            if ($authUser->hasPermissionTo(perm_key('admin.super'))) {
                // المسؤول العام يرى جميع خطط التقسيط (لا توجد قيود إضافية على الاستعلام)
            } elseif ($authUser->hasAnyPermission([perm_key('installment_plans.view_all'), perm_key('admin.company')])) {
                $query->whereCompanyIsCurrent();
            } elseif ($authUser->hasPermissionTo(perm_key('installment_plans.view_children'))) {
                $query->whereCompanyIsCurrent()->whereCreatedByUserOrChildren();
            } elseif ($authUser->hasPermissionTo(perm_key('installment_plans.view_self'))) {
                $query->whereCompanyIsCurrent()->whereCreatedByUser();
            } else {
                // العميل: يرى الخطط الخاصة به
                $query->where('user_id', $authUser->id);
            }

            // ✅ التصفية بناءً على حالة القسط
            if ($request->filled('status')) {
                $query->where('status', $request->input('status'));
            } else {
                $query->where('status', '!=', 'canceled');
            }

            // ✅ فلاتر إضافية
            if ($request->filled('invoice_id')) {
                $query->where('invoice_id', $request->input('invoice_id'));
            }
            if ($request->filled('user_id')) {
                $query->where('user_id', $request->input('user_id'));
            }

            // ✅ منطق البحث الذكي (رقم الفاتورة، الاسم، الهاتف)
            if ($request->filled('search')) {
                $search = trim($request->input('search'));
                $query->smartSearch($search, ['id'], [
                    'customer' => ['full_name', 'nickname', 'phone'],
                    'invoice' => ['invoice_number']
                ]);
            }


            // ✅ تحديد عدد العناصر في الصفحة والفرز
            $perPage = (int) $request->input('per_page', 20);

            // Whitelist for sorting to prevent SQL injection or unknown column errors
            $allowedSortFields = [
                'id',
                'created_at',
                'start_date',
                'end_date',
                'total_amount',
                'remaining_amount',
                'status',
                'installment_amount'
            ];

            $sortField = $request->input('sort_by', 'created_at');
            $sortOrder = $request->input('sort_order', 'desc');

            // Map frontend virtual fields to physical backend columns
            if ($sortField === 'due_date') {
                $sortField = 'start_date';
            }

            // Fallback to created_at if sort field is not allowed
            if (!in_array($sortField, $allowedSortFields)) {
                $sortField = 'created_at';
            }

            $query->orderBy($sortField, $sortOrder);

            // ✅ جلب البيانات مع أو بدون الباجينيشن
            $plans = $perPage == -1 ? $query->get() : $query->paginate(max(1, $perPage));

            // تحسين النتائج بالتشابه (Similarity Refinement)
            if ($request->filled('search') && $plans->isNotEmpty()) {
                $search = $request->input('search');
                $fieldsToCompare = ['id', 'customer.full_name', 'customer.nickname', 'customer.phone', 'invoice.invoice_number'];

                $items = $plans instanceof \Illuminate\Pagination\LengthAwarePaginator ? collect($plans->items()) : $plans;

                $refined = (new InstallmentPlan())->refineSimilarity($items, $search, $fieldsToCompare, 80);

                if ($plans instanceof \Illuminate\Pagination\LengthAwarePaginator) {
                    /** @var \Illuminate\Pagination\LengthAwarePaginator $plans */
                    $plans->setCollection($refined);
                } else {
                    $plans = $refined;
                }
            }

            // ✅ بناء الاستجابة
            if ($plans->isEmpty()) {
                return api_success([], 'لم يتم العثور على خطط تقسيط.');
            } else {
                return api_success(InstallmentPlanResource::collection($plans), 'تم جلب خطط التقسيط بنجاح.');
            }
        } catch (Throwable $e) {
            return api_exception($e, 500);
        }
    }


    /**
     * @group 04. نظام الأقساط
     * 
     * إنشاء خطة تقسيط
     * 
     * @bodyParam user_id integer required معرف العميل. Example: 1
     * @bodyParam total_amount number required إجمالي المبلغ. Example: 5000
     * @bodyParam installment_count integer required عدد الأقساط. Example: 10
     */
    public function store(StoreInstallmentPlanRequest $request): JsonResponse
    {
        try {
            /** @var \App\Models\User $authUser */
            $authUser = Auth::user();
            $companyId = $authUser->company_id ?? null;

            // التحقق الأساسي: إذا لم يكن المستخدم مرتبطًا بشركة
            if (!$authUser || !$companyId) {
                return api_unauthorized('يتطلب المصادقة أو الارتباط بالشركة.');
            }

            // صلاحيات إنشاء خطة تقسيط
            if (!$authUser->hasPermissionTo(perm_key('admin.super')) && !$authUser->hasPermissionTo(perm_key('installment_plans.create')) && !$authUser->hasPermissionTo(perm_key('admin.company'))) {
                return api_forbidden('ليس لديك إذن لإنشاء خطط تقسيط.');
            }

            DB::beginTransaction();
            try {
                $validatedData = $request->validated();

                // تعيين created_by و company_id تلقائيًا
                $validatedData['created_by'] = $authUser->id;
                // التأكد من أن خطة التقسيط تابعة لشركة المستخدم الحالي
                if (isset($validatedData['company_id']) && $validatedData['company_id'] != $companyId) {
                    DB::rollBack();
                    return api_forbidden('يمكنك فقط إنشاء خطط تقسيط لشركتك الحالية.');
                }
                $validatedData['company_id'] = $companyId; // التأكد من ربط خطة التقسيط بالشركة النشطة

                $plan = InstallmentPlan::create($validatedData);
                $plan->load($this->relations);
                DB::commit();
                return api_success(new InstallmentPlanResource($plan), 'تم إنشاء خطة التقسيط بنجاح.', 201);
            } catch (ValidationException $e) {
                DB::rollBack();
                return api_error('فشل التحقق من صحة البيانات أثناء تخزين خطة التقسيط.', $e->errors(), 422);
            } catch (Throwable $e) {
                DB::rollBack();
                return api_error('حدث خطأ أثناء حفظ خطة التقسيط.', [], 500);
            }
        } catch (Throwable $e) {
            return api_exception($e, 500);
        }
    }

    /**
     * @group 04. نظام الأقساط
     * 
     * تفاصيل خطة تقسيط
     * 
     * @urlParam installmentPlan required معرف الخطة. Example: 1
     * 
     * @apiResource App\Http\Resources\InstallmentPlan\InstallmentPlanResource
     * @apiResourceModel App\Models\InstallmentPlan
     */
    public function show(InstallmentPlan $installmentPlan): JsonResponse
    {
        try {
            /** @var \App\Models\User $authUser */
            $authUser = Auth::user();
            $companyId = $authUser->company_id ?? null;

            if (!$authUser || !$companyId) {
                return api_unauthorized('يتطلب المصادقة أو الارتباط بالشركة.');
            }

            // Load relations for the already resolved InstallmentPlan model
            // Load relations with priority ordering for installments
            $installmentPlan->load([
                'customer',
                'creator',
                'invoice.items.variant',
                'installments' => function ($query) {
                    $query->orderByPriority();
                },
                'company',
            ]);

            // التحقق من صلاحيات العرض
            $canView = false;
            if ($authUser->hasPermissionTo(perm_key('admin.super'))) {
                $canView = true; // المسؤول العام يرى أي خطة تقسيط
            } elseif ($authUser->hasAnyPermission([perm_key('installment_plans.view_all'), perm_key('admin.company')])) {
                // يرى إذا كانت خطة التقسيط تنتمي للشركة النشطة (بما في ذلك مديرو الشركة)
                $canView = $installmentPlan->belongsToCurrentCompany();
            } elseif ($authUser->hasPermissionTo(perm_key('installment_plans.view_children'))) {
                // يرى إذا كانت خطة التقسيط أنشأها هو أو أحد التابعين له وتابعة للشركة النشطة
                $canView = $installmentPlan->belongsToCurrentCompany() && $installmentPlan->createdByUserOrChildren();
            } elseif ($authUser->hasPermissionTo(perm_key('installment_plans.view_self'))) {
                // يرى إذا كانت خطة التقسيط أنشأها هو وتابعة للشركة النشطة
                $canView = $installmentPlan->belongsToCurrentCompany() && $installmentPlan->createdByCurrentUser();
            }

            if ($canView) {
                return api_success(new InstallmentPlanResource($installmentPlan), 'تم استرداد خطة التقسيط بنجاح.');
            }

            return api_forbidden('ليس لديك إذن لعرض خطة التقسيط هذه.');
        } catch (Throwable $e) {
            return api_exception($e, 500);
        }
    }

    /**
     * @group 04. نظام الأقساط
     * 
     * تحديث خطة تقسيط
     * 
     * @urlParam id required معرف الخطة. Example: 1
     * @bodyParam status string الحالة الجديدة.
     */
    public function update(UpdateInstallmentPlanRequest $request, string $id): JsonResponse
    {
        try {
            /** @var \App\Models\User $authUser */
            $authUser = Auth::user();
            $companyId = $authUser->company_id ?? null;

            if (!$authUser || !$companyId) {
                return api_unauthorized('يتطلب المصادقة أو الارتباط بالشركة.');
            }

            // يجب تحميل العلاقات الضرورية للتحقق من الصلاحيات (مثل الشركة والمنشئ)
            $plan = InstallmentPlan::with(['company', 'creator'])->findOrFail($id);

            // التحقق من صلاحيات التحديث
            $canUpdate = false;
            if ($authUser->hasPermissionTo(perm_key('admin.super'))) {
                $canUpdate = true; // المسؤول العام يمكنه تعديل أي خطة تقسيط
            } elseif ($authUser->hasAnyPermission([perm_key('installment_plans.update_all'), perm_key('admin.company')])) {
                // يمكنه تعديل أي خطة تقسيط داخل الشركة النشطة (بما في ذلك مديرو الشركة)
                $canUpdate = $plan->belongsToCurrentCompany();
            } elseif ($authUser->hasPermissionTo(perm_key('installment_plans.update_children'))) {
                // يمكنه تعديل خطط التقسيط التي أنشأها هو أو أحد التابعين له وتابعة للشركة النشطة
                $canUpdate = $plan->belongsToCurrentCompany() && $plan->createdByUserOrChildren();
            } elseif ($authUser->hasPermissionTo(perm_key('installment_plans.update_self'))) {
                // يمكنه تعديل خطة تقسيطه الخاصة التي أنشأها وتابعة للشركة النشطة
                $canUpdate = $plan->belongsToCurrentCompany() && $plan->createdByCurrentUser();
            }

            if (!$canUpdate) {
                return api_forbidden('ليس لديك إذن لتحديث خطة التقسيط هذه.');
            }

            DB::beginTransaction();
            try {
                $validatedData = $request->validated();
                $validatedData['updated_by'] = $authUser->id;

                $plan->update($validatedData);
                $plan->load($this->relations); // إعادة تحميل العلاقات بعد التحديث
                DB::commit();
                return api_success(new InstallmentPlanResource($plan), 'تم تحديث خطة التقسيط بنجاح.');
            } catch (ValidationException $e) {
                DB::rollBack();
                return api_error('فشل التحقق من صحة البيانات أثناء تحديث خطة التقسيط.', $e->errors(), 422);
            } catch (Throwable $e) {
                DB::rollBack();
                return api_error('حدث خطأ أثناء تحديث خطة التقسيط.', [], 500);
            }
        } catch (Throwable $e) {
            return api_exception($e, 500);
        }
    }

    /**
     * @group 04. نظام الأقساط
     * 
     * حذف خطة تقسيط
     * 
     * @urlParam id required معرف الخطة. Example: 1
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

            // يجب تحميل العلاقات الضرورية للتحقق من الصلاحيات (مثل الشركة والمنشئ)
            $plan = InstallmentPlan::with(['company', 'creator', 'installments'])->findOrFail($id);

            // التحقق من صلاحيات الحذف
            $canDelete = false;
            if ($authUser->hasPermissionTo(perm_key('admin.super'))) {
                $canDelete = true;
            } elseif ($authUser->hasAnyPermission([perm_key('installment_plans.delete_all'), perm_key('admin.company')])) {
                // يمكنه حذف أي خطة تقسيط داخل الشركة النشطة (بما في ذلك مديرو الشركة)
                $canDelete = $plan->belongsToCurrentCompany();
            } elseif ($authUser->hasPermissionTo(perm_key('installment_plans.delete_children'))) {
                // يمكنه حذف خطط التقسيط التي أنشأها هو أو أحد التابعين له وتابعة للشركة النشطة
                $canDelete = $plan->belongsToCurrentCompany() && $plan->createdByUserOrChildren();
            } elseif ($authUser->hasPermissionTo(perm_key('installment_plans.delete_self'))) {
                // يمكنه حذف خطة تقسيطه الخاصة التي أنشأها وتابعة للشركة النشطة
                $canDelete = $plan->belongsToCurrentCompany() && $plan->createdByCurrentUser();
            }

            if (!$canDelete) {
                return api_forbidden('ليس لديك إذن لحذف خطة التقسيط هذه.');
            }

            DB::beginTransaction();
            try {
                // تحقق مما إذا كانت خطة التقسيط مرتبطة بأي أقساط
                if ($plan->installments()->exists()) {
                    DB::rollBack();
                    return api_error('لا يمكن حذف خطة التقسيط. إنها مرتبطة بأقساط موجودة.', [], 409);
                }

                $deletedPlan = $plan->replicate(); // نسخ الكائن قبل الحذف
                $deletedPlan->setRelations($plan->getRelations()); // نسخ العلاقات المحملة

                $plan->delete();
                DB::commit();
                return api_success(new InstallmentPlanResource($deletedPlan), 'تم حذف خطة التقسيط بنجاح.');
            } catch (Throwable $e) {
                DB::rollBack();
                return api_error('حدث خطأ أثناء حذف خطة التقسيط.', [], 500);
            }
        } catch (Throwable $e) {
            return api_exception($e, 500);
        }
    }
}
