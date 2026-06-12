<?php

namespace App\Http\Controllers;

use App\Models\Company;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use App\Http\Requests\Company\CompanyRequest;
use App\Http\Requests\Company\CompanyUpdateRequest;
use App\Http\Resources\Company\CompanyResource;

/**
 * متحكم إدارة الشركات — CRUD كامل مع حماية صلاحيات متدرجة (super > view_all > view_children > view_self).
 * يدعم التصفية والترتيب والتصفح مع دعم كامل للـ Multi-Tenant isolation.
 */
class CompanyController extends Controller
{
    // =========================================================================
    // الـ Relations التي تُحمّل مع كل شركة
    // =========================================================================
    private array $relations = ['logo'];

    // =========================================================================
    // قراءة القائمة
    // =========================================================================

    /**
     * عرض قائمة الشركات بحسب صلاحية المستخدم مع دعم الفلاتر والترتيب.
     *
     * الصلاحيات:
     *  - admin.super      → جميع الشركات بدون أي فلتر
     *  - companies.view_all   → جميع الشركات (نفس السوبر عملياً)
     *  - companies.view_children → الشركات التي أنشأها المستخدم أو مرؤوسيه
     *  - companies.view_self     → الشركات التي أنشأها المستخدم فقط
     *  - أي شيء آخر         → 403 Forbidden
     *
     * @group 07. الإدارة وسجلات النظام
     * @queryParam per_page    integer  عدد النتائج في الصفحة. Default: 10
     * @queryParam sort_by     string   حقل الترتيب. Default: id
     * @queryParam sort_order  string   asc | desc. Default: asc
     * @queryParam created_at_from string فلتر من تاريخ (Y-m-d)
     * @queryParam created_at_to   string فلتر إلى تاريخ (Y-m-d)
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $user = Auth::user();
            $query = Company::withoutGlobalScopes()
                ->whereNull('deleted_at')
                ->with($this->relations)
                ->withCount('branches');

            // ---- تطبيق نطاق الرؤية بحسب الصلاحية ----
            if (
                $user->hasPermissionTo(perm_key('admin.super')) ||
                $user->hasPermissionTo(perm_key('companies.view_all'))
            ) {
                // وصول مطلق — لا فلتر
            } elseif ($user->hasPermissionTo(perm_key('companies.view_children'))) {
                // يرى ما أنشأه هو أو مرؤوسيه
                $descendantIds = method_exists($user, 'getDescendantUserIds')
                    ? $user->getDescendantUserIds()
                    : [];
                $query->whereIn('created_by', array_merge([$user->id], $descendantIds));
            } elseif ($user->hasPermissionTo(perm_key('companies.view_self'))) {
                // يرى ما أنشأه هو فقط
                $query->where('created_by', $user->id);
            } else {
                return api_forbidden('ليس لديك صلاحية لعرض الشركات.');
            }

            // ---- الفلاتر الاختيارية ----
            if ($from = $request->get('created_at_from')) {
                $query->where('created_at', '>=', $from . ' 00:00:00');
            }
            if ($to = $request->get('created_at_to')) {
                $query->where('created_at', '<=', $to . ' 23:59:59');
            }
            if ($search = $request->get('search')) {
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'LIKE', "%{$search}%")
                        ->orWhere('email', 'LIKE', "%{$search}%")
                        ->orWhere('phone', 'LIKE', "%{$search}%");
                });
            }

            // ---- الترتيب والتصفح ----
            $allowedSortFields = ['id', 'name', 'email', 'created_at'];
            $sortField = in_array($request->get('sort_by'), $allowedSortFields) ? $request->get('sort_by') : 'id';
            $sortOrder = in_array($request->get('sort_order'), ['asc', 'desc']) ? $request->get('sort_order') : 'asc';
            $perPage = max(1, min(200, (int) $request->get('per_page', 15)));

            $companies = $query->orderBy($sortField, $sortOrder)->paginate($perPage);

            return api_success(
                CompanyResource::collection($companies),
                $companies->isEmpty() ? 'لم يتم العثور على شركات.' : 'تم جلب الشركات بنجاح.'
            );
        } catch (\Exception $e) {
            return api_exception($e);
        }
    }

    // =========================================================================
    // عرض شركة واحدة
    // =========================================================================

    /**
     * عرض تفاصيل شركة محددة.
     *
     * الصلاحيات:
     *  - admin.super / view_all     → أي شركة
     *  - admin.company / view_children → شركته أو التابعة له
     *  - view_self                  → شركته التي أنشأها هو فقط
     *
     * @group 07. الإدارة وسجلات النظام
     * @urlParam company int required معرف الشركة. Example: 1
     */
    public function show(Company $company): JsonResponse
    {
        $user = Auth::user();

        if (!$this->canView($user, $company)) {
            return api_forbidden('ليس لديك صلاحية لعرض هذه الشركة.');
        }

        return api_success(
            new CompanyResource($company->loadMissing($this->relations)),
            'تم جلب بيانات الشركة بنجاح.'
        );
    }

    // =========================================================================
    // إنشاء شركة
    // =========================================================================

    /**
     * إنشاء شركة جديدة في النظام.
     *
     * الصلاحيات: admin.super | companies.create | admin.company
     *
     * @group 07. الإدارة وسجلات النظام
     * @bodyParam name string required اسم الشركة. Example: شركة التقدم
     */
    public function store(CompanyRequest $request): JsonResponse
    {
        $user = Auth::user();

        if (
            !$user->hasAnyPermission([
                perm_key('admin.super'),
                perm_key('companies.create'),
            ])
        ) {
            return api_forbidden('ليس لديك صلاحية لإنشاء شركة جديدة.');
        }

        try {
            $company = DB::transaction(function () use ($request, $user) {
                $data = $request->validated();
                $data['created_by'] = $data['created_by'] ?? $user->id;
                $data['company_id'] = $data['company_id'] ?? $user->active_company_id;

                $company = Company::create($data);

                // ربط المستخدم المُنشئ بالشركة تلقائياً
                $company->users()->attach($user->id, ['created_by' => $user->id]);

                // تحديث الشركة النشطة للمستخدم إلى الشركة الجديدة
                $user->update(['active_company_id' => $company->id]);

                // مزامنة الصور (اللوغو) إن وجدت
                if ($request->filled('images_ids')) {
                    $company->syncImages($request->input('images_ids'), 'logo');
                }

                return $company;
            });

            return api_success(
                new CompanyResource($company->load($this->relations)),
                'تم إنشاء الشركة بنجاح.',
                201
            );
        } catch (\Exception $e) {
            return api_exception($e);
        }
    }

    // =========================================================================
    // تحديث شركة
    // =========================================================================

    /**
     * تحديث بيانات شركة محددة.
     *
     * الصلاحيات:
     *  - admin.super / update_all     → تعديل أي شركة
     *  - admin.company / update_children → تعديل شركاته أو التابعة له
     *  - update_self                  → تعديل ما أنشأه هو فقط
     *
     * @group 07. الإدارة وسجلات النظام
     * @urlParam company int required معرف الشركة. Example: 1
     */
    public function update(CompanyUpdateRequest $request, Company $company): JsonResponse
    {
        $user = Auth::user();

        if (!$this->canUpdate($user, $company)) {
            return api_forbidden('ليس لديك صلاحية لتحديث هذه الشركة.');
        }

        try {
            DB::transaction(function () use ($request, $company) {
                $company->update($request->validated());

                if ($request->filled('images_ids')) {
                    $company->syncImages($request->input('images_ids'), 'logo');
                }
            });

            return api_success(
                new CompanyResource($company->fresh()->load($this->relations)),
                'تم تحديث بيانات الشركة بنجاح.'
            );
        } catch (\Exception $e) {
            return api_exception($e);
        }
    }

    // =========================================================================
    // حذف شركات (Batch)
    // =========================================================================

    /**
     * حذف شركة أو مجموعة شركات (Batch Delete).
     *
     * الصلاحيات:
     *  - admin.super / delete_all     → حذف أي شركة
     *  - admin.company / delete_children → حذف ما يتبعه فقط
     *  - delete_self                  → حذف ما أنشأه هو فقط
     *
     * ⚠️ لا يمكن حذف الشركة النشطة الحالية للمستخدم.
     *
     * @group 07. الإدارة وسجلات النظام
     * @bodyParam item_ids int[] required مصفوفة معرفات الشركات. Example: [1, 2]
     */
    public function destroy(Request $request): JsonResponse
    {
        $user = Auth::user();

        $request->validate([
            'item_ids' => 'required|array|min:1',
            'item_ids.*' => 'integer|exists:companies,id',
        ]);

        $companyIds = $request->input('item_ids');
        $companies = Company::withoutGlobalScopes()->whereIn('id', $companyIds)->get();

        // التحقق من الصلاحية على كل شركة
        foreach ($companies as $company) {
            if (!$this->canDelete($user, $company)) {
                return api_forbidden("ليس لديك صلاحية لحذف الشركة: {$company->name} (#{$company->id}).");
            }
        }

        // منع حذف الشركة النشطة للمستخدم
        if (in_array($user->active_company_id, $companyIds)) {
            return api_error(
                'لا يمكن حذف الشركة النشطة الحالية. يرجى تغيير الشركة النشطة أولاً.',
                [],
                422
            );
        }

        try {
            DB::transaction(function () use ($companies) {
                foreach ($companies as $company) {
                    $company->delete();
                }
            });

            return api_success(null, 'تم نقل الشركات المحددة إلى سلة المحذوفات بنجاح.');
        } catch (\Exception $e) {
            return api_exception($e);
        }
    }

    /**
     * عرض سلة محذوفات الشركات (Trash).
     *
     * الصلاحيات: admin.super | companies.delete_all
     *
     * @group 07. الإدارة وسجلات النظام
     */
    public function trash(Request $request): JsonResponse
    {
        $user = Auth::user();

        if (
            !$user->hasAnyPermission([
                perm_key('admin.super'),
                perm_key('companies.delete_all'),
            ])
        ) {
            return api_forbidden('ليس لديك صلاحية لعرض سلة المحذوفات.');
        }

        try {
            $perPage = max(1, min(200, (int) $request->get('per_page', 15)));
            $companies = Company::onlyTrashed()
                ->withoutGlobalScopes()
                ->with($this->relations)
                ->orderBy('deleted_at', 'desc')
                ->paginate($perPage);

            return api_success(
                CompanyResource::collection($companies),
                $companies->isEmpty() ? 'سلة المحذوفات فارغة.' : 'تم جلب سلة المحذوفات بنجاح.'
            );
        } catch (\Exception $e) {
            return api_exception($e);
        }
    }

    /**
     * استرجاع الشركات المحددة من سلة المحذوفات.
     *
     * الصلاحيات: admin.super | companies.delete_all
     *
     * @group 07. الإدارة وسجلات النظام
     * @bodyParam item_ids int[] required مصفوفة معرفات الشركات. Example: [1, 2]
     */
    public function restore(Request $request): JsonResponse
    {
        $user = Auth::user();

        if (
            !$user->hasAnyPermission([
                perm_key('admin.super'),
                perm_key('companies.delete_all'),
            ])
        ) {
            return api_forbidden('ليس لديك صلاحية لاسترجاع الشركات.');
        }

        $request->validate([
            'item_ids' => 'required|array|min:1',
            'item_ids.*' => 'integer',
        ]);

        try {
            $companyIds = $request->input('item_ids');

            DB::transaction(function () use ($companyIds) {
                Company::onlyTrashed()
                    ->withoutGlobalScopes()
                    ->whereIn('id', $companyIds)
                    ->restore();
            });

            return api_success(null, 'تم استرجاع الشركات المحددة بنجاح.');
        } catch (\Exception $e) {
            return api_exception($e);
        }
    }

    /**
     * حذف الشركات نهائياً وتطهير سجلاتها من النظام (Force Delete).
     *
     * الصلاحيات: admin.super | companies.delete_all
     *
     * @group 07. الإدارة وسجلات النظام
     * @bodyParam item_ids int[] required مصفوفة معرفات الشركات. Example: [1, 2]
     */
    public function forceDestroy(Request $request): JsonResponse
    {
        $user = Auth::user();

        if (
            !$user->hasAnyPermission([
                perm_key('admin.super'),
                perm_key('companies.delete_all'),
            ])
        ) {
            return api_forbidden('ليس لديك صلاحية لحذف الشركات نهائياً.');
        }

        $request->validate([
            'item_ids' => 'required|array|min:1',
            'item_ids.*' => 'integer',
        ]);

        try {
            $companyIds = $request->input('item_ids');

            // جلب الشركات المحذوفة مؤقتاً للتأكد من وجودها
            $companies = Company::onlyTrashed()
                ->withoutGlobalScopes()
                ->whereIn('id', $companyIds)
                ->get();

            if ($companies->isEmpty()) {
                return api_error('لم يتم العثور على أي شركة محددة في سلة المحذوفات.', [], 404);
            }

            DB::transaction(function () use ($companies) {
                foreach ($companies as $company) {
                    $company->forceDelete();
                }
            });

            return api_success(null, 'تم حذف الشركات وتطهير سجلاتها نهائياً من النظام.');
        } catch (\Exception $e) {
            return api_exception($e);
        }
    }

    // =========================================================================
    // دوال خاصة للتحقق من الصلاحيات — مركزية لتسهيل الصيانة
    // =========================================================================

    /**
     * هل يملك المستخدم صلاحية عرض هذه الشركة؟
     */
    private function canView($user, Company $company): bool
    {
        if (
            $user->hasPermissionTo(perm_key('admin.super')) ||
            $user->hasPermissionTo(perm_key('companies.view_all'))
        ) {
            return true;
        }

        if ($user->hasPermissionTo(perm_key('companies.view_children'))) {
            $descendantIds = method_exists($user, 'getDescendantUserIds') ? $user->getDescendantUserIds() : [];
            if (in_array($company->created_by, array_merge([$user->id], $descendantIds))) {
                return true;
            }
        }

        if (
            $user->hasPermissionTo(perm_key('companies.view_self')) &&
            $company->isSelf()
        ) {
            return true;
        }

        if (
            $user->hasPermissionTo(perm_key('admin.company')) &&
            $company->isCurrentCompany()
        ) {
            return true;
        }

        return false;
    }

    /**
     * هل يملك المستخدم صلاحية تعديل هذه الشركة؟
     */
    private function canUpdate($user, Company $company): bool
    {
        if (
            $user->hasPermissionTo(perm_key('admin.super')) ||
            $user->hasPermissionTo(perm_key('companies.update_all'))
        ) {
            return true;
        }

        if ($user->hasPermissionTo(perm_key('companies.update_children'))) {
            $descendantIds = method_exists($user, 'getDescendantUserIds') ? $user->getDescendantUserIds() : [];
            if (in_array($company->created_by, array_merge([$user->id], $descendantIds))) {
                return true;
            }
        }

        if (
            $user->hasPermissionTo(perm_key('companies.update_self')) &&
            $company->isSelf()
        ) {
            return true;
        }

        if (
            $user->hasPermissionTo(perm_key('admin.company')) &&
            $company->isCurrentCompany()
        ) {
            return true;
        }

        return false;
    }

    /**
     * هل يملك المستخدم صلاحية حذف هذه الشركة؟
     */
    private function canDelete($user, Company $company): bool
    {
        if (
            $user->hasPermissionTo(perm_key('admin.super')) ||
            $user->hasPermissionTo(perm_key('companies.delete_all'))
        ) {
            return true;
        }

        if ($user->hasPermissionTo(perm_key('companies.delete_children'))) {
            $descendantIds = method_exists($user, 'getDescendantUserIds') ? $user->getDescendantUserIds() : [];
            if (in_array($company->created_by, array_merge([$user->id], $descendantIds))) {
                return true;
            }
        }

        if (
            $user->hasPermissionTo(perm_key('companies.delete_self')) &&
            $company->isSelf()
        ) {
            return true;
        }

        return false;
    }

    /**
     *   جلب بيانات الشركة الأولى في النظام لعرضها على صفحات الهبوط العامة.
     */
    public function publicCompany(): JsonResponse
    {
        try {
            $company = Company::withoutGlobalScopes()->with($this->relations)->first();

            if (!$company) {
                return api_error('لم يتم العثور على أي شركة في النظام.', 404);
            }

            return api_success(new CompanyResource($company), 'تم جلب بيانات الهوية العامة بنجاح.');
        } catch (\Exception $e) {
            return api_exception($e);
        }
    }
}
