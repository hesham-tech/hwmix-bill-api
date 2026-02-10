<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\Warehouse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\Scopes\CompanyScope;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use App\Http\Requests\Company\CompanyRequest;
use App\Http\Resources\Company\CompanyResource;
use App\Http\Requests\Company\CompanyUpdateRequest;

class CompanyController extends Controller
{
    private array $relations;

    public function __construct()
    {
        $this->relations = [
            'logo',
        ];
    }

    /**
     * @group 07. الإدارة وسجلات النظام
     * 
     * عرض قائمة الشركات
     * 
     * @queryParam per_page integer عدد النتائج.
     */
    public function index(Request $request)
    {
        try {
            $authUser = Auth::user();
            $query = Company::with($this->relations);

            if ($authUser->hasPermissionTo(perm_key('admin.super')) || $authUser->hasPermissionTo(perm_key('companies.view_all'))) {
                // وصول مطلق
            } elseif ($authUser->hasPermissionTo(perm_key('admin.company'))) {
                // يرى شركته والشركات المرتبطة به
                $myCompanyIds = $authUser->companies->pluck('id')->toArray();
                $query->whereIn('id', $myCompanyIds);
            } elseif ($authUser->hasPermissionTo(perm_key('companies.view_children'))) {
                // يرى فقط ما يتبع لشجرته
                $descendantIds = $authUser->getDescendantUserIds();
                $query->whereIn('created_by', array_merge([$authUser->id], $descendantIds));
            } elseif ($authUser->hasPermissionTo(perm_key('companies.view_self'))) {
                $query->where('created_by', $authUser->id);
            } else {
                return api_forbidden('ليس لديك صلاحية لعرض الشركات.');
            }

            if (!empty($request->get('created_at_from'))) {
                $query->where('created_at', '>=', $request->get('created_at_from') . ' 00:00:00');
            }
            if (!empty($request->get('created_at_to'))) {
                $query->where('created_at', '<=', $request->get('created_at_to') . ' 23:59:59');
            }

            $perPage = max(1, $request->get('per_page', 10));
            $sortField = $request->get('sort_by', 'id');
            $sortOrder = $request->get('sort_order', 'asc');
            $companies = $query->orderBy($sortField, $sortOrder)->paginate($perPage);

            if ($companies->isEmpty()) {
                return api_success([], 'لم يتم العثور على شركات.'); // استخدم مصفوفة فارغة إذا لم يتم العثور على شركات
            } else {
                return api_success(CompanyResource::collection($companies), 'تم جلب الشركات بنجاح.');
            }
        } catch (\Exception $e) {
            return api_exception($e);
        }
    }

    /**
     * @group 07. الإدارة وسجلات النظام
     * 
     * إضافة شركة جديدة
     * 
     * @bodyParam name string required اسم الشركة. Example: شركة التقدم
     */
    public function store(CompanyRequest $request)
    {
        $authUser = Auth::user();
        if (
            !$authUser->hasAnyPermission([
                perm_key('admin.super'),
                perm_key('companies.create'),
                perm_key('admin.company')
            ])
        ) {
            return api_forbidden('ليس لديك صلاحية لإنشاء شركة.');
        }

        $validatedData = $request->validated();
        try {
            DB::beginTransaction();

            $validatedData['company_id'] = $validatedData['company_id'] ?? $authUser->company_id;
            $validatedData['created_by'] = $validatedData['created_by'] ?? $authUser->id;


            $company = Company::create($validatedData);
            $company->users()->attach($authUser->id, ['created_by' => $authUser->id]);
            if ($request->has('images_ids')) {
                $imagesIds = $request->input('images_ids');
                $company->syncImages($imagesIds, 'logo');
            } else {
                // اضافة صوره افتراضية
                // new \Illuminate\Http\UploadedFile(public_path('images/default-logo.png'), 'default-logo.png');
            }

            $authUser->company_id = $company->id;
            $authUser->save();

            DB::commit();
            return api_success(new CompanyResource($company->load($this->relations)), 'تم إنشاء الشركة بنجاح');
        } catch (\Exception $e) {
            DB::rollback();
            return api_exception($e);
        }
    }

    /**
     * @group 07. الإدارة وسجلات النظام
     * 
     * عرض تفاصيل شركة
     * 
     * @urlParam company required معرف الشركة. Example: 1
     */
    public function show(Company $company)
    {
        $authUser = Auth::user();

        if ($authUser->hasPermissionTo(perm_key('admin.super'))) {
            return api_success(new CompanyResource(Company::withoutGlobalScope(CompanyScope::class)->findOrFail($company->id)), 'تم جلب بيانات الشركة بنجاح');
        }

        if (
            $authUser->hasPermissionTo(perm_key('companies.view_all')) ||
            ($authUser->hasPermissionTo(perm_key('companies.view_children')) && $company->isOwn()) ||
            ($authUser->hasPermissionTo(perm_key('companies.view_self')) && $company->isSelf()) ||
            ($authUser->hasPermissionTo(perm_key('admin.company')) && ($company->isCurrentCompany() || $company->isOwn()))
        ) {
            return api_success(new CompanyResource($company->load($this->relations)), 'تم جلب بيانات الشركة بنجاح');
        }

        return api_forbidden('ليس لديك صلاحية لعرض هذه الشركة.');
    }

    public function update(CompanyUpdateRequest $request, Company $company)
    {
        $authUser = Auth::user();
        $validated = $request->validated();
        if (
            $authUser->hasPermissionTo(perm_key('admin.super')) ||
            $authUser->hasPermissionTo(perm_key('companies.update_all')) ||
            ($authUser->hasPermissionTo(perm_key('companies.update_children')) && $company->isOwn()) ||
            ($authUser->hasPermissionTo(perm_key('companies.update_self')) && $company->isSelf()) ||
            ($authUser->hasPermissionTo(perm_key('admin.company')) && ($company->isCurrentCompany() || $company->isOwn()))
        ) {
            try {
                DB::beginTransaction();
                $company->update($validated);

                if ($request->has('images_ids')) {
                    $imagesIds = $request->input('images_ids');
                    $company->syncImages($imagesIds, 'logo');
                }
                DB::commit();
                return api_success(new CompanyResource($company->load($this->relations)), 'تم تحديث الشركة بنجاح');
            } catch (\Exception $e) {
                DB::rollback();
                return api_exception($e);
            }
        }

        return api_forbidden('ليس لديك صلاحية لتحديث هذه الشركة.');
    }

    /**
     * @group 07. الإدارة وسجلات النظام
     * 
     * حذف شركات (Batch)
     * 
     * @bodyParam item_ids integer[] required مصفوفة المعرفات.
     */
    public function destroy(Request $request)
    {
        $authUser = Auth::user();
        $companyIds = $request->input('item_ids');

        if (!$companyIds || !is_array($companyIds)) {
            return api_error('لم يتم تحديد معرفات الشركات بشكل صحيح', [], 400);
        }

        $companiesToDelete = Company::whereIn('id', $companyIds)->get();

        foreach ($companiesToDelete as $company) {
            if (
                !(
                    $authUser->hasPermissionTo(perm_key('admin.super')) ||
                    $authUser->hasPermissionTo(perm_key('companies.delete_all')) ||
                    ($authUser->hasPermissionTo(perm_key('companies.delete_children')) && $company->isOwn()) ||
                    ($authUser->hasPermissionTo(perm_key('companies.delete_self')) && $company->isSelf()) ||
                    ($authUser->hasPermissionTo(perm_key('admin.company')) && ($company->isCurrentCompany() || $company->isOwn()))
                )
            ) {
                return api_forbidden('ليس لديك صلاحية لحذف الشركة ذات المعرف: ' . $company->id);
            }
        }

        try {
            DB::beginTransaction();
            foreach ($companiesToDelete as $company) {
                if ($logo = $company->images()->where('type', 'logo')->first()) {
                    $company->deleteImage($logo);
                }
                $company->delete();
            }
            DB::commit();
            return api_success([], 'تم حذف الشركات بنجاح');
        } catch (\Exception $e) {
            DB::rollback();
            return api_exception($e);
        }
    }
}
