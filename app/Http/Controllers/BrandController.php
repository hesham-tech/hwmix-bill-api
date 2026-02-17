<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Http\Requests\Brand\StoreBrandRequest;
use App\Http\Requests\Brand\UpdateBrandRequest;
use App\Http\Resources\Brand\BrandResource;
use App\Models\Brand;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use App\Services\ImageService;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Class BrandController
 *
 * تحكم في عمليات العلامات التجارية (عرض، إضافة، تعديل، حذف)
 *
 * @package App\Http\Controllers
 */
class BrandController extends Controller
{
    protected array $relations;

    public function __construct()
    {
        $this->relations = [
            'creator',
            'company',
            'products',
            'image',
        ];
    }

    /**
     * @group 03. إدارة المنتجات والمخزون
     * 
     * عرض قائمة الماركات
     * 
     * استرجاع قائمة العلامات التجارية المسجلة في النظام.
     * 
     * @queryParam search string البحث باسم الماركة أو وصفها. Example: سامسونج
     * 
     * @apiResourceCollection App\Http\Resources\Brand\BrandResource
     * @apiResourceModel App\Models\Brand
     */
    public function index(Request $request): JsonResponse
    {
        try {
            /** @var \App\Models\User $authUser */
            $authUser = Auth::user();
            $query = Brand::with($this->relations);
            $companyId = $authUser->company_id ?? null;
            // تطبيق منطق الصلاحيات + تضمين العلامات العالمية
            if ($authUser->hasPermissionTo(perm_key('admin.super'))) {
                // المسؤول العام يرى كل شيء
            } else {
                // المستخدم العادي يرى ماركات شركته + العلامات العالمية (NULL company_id)
                $query->where(function ($q) use ($authUser) {
                    $q->whereCompanyIsCurrent()
                        ->orWhereNull('company_id');
                });
            }

            // تطبيق فلاتر البحث باستخدام الـ scope الجديد
            if ($request->filled('search')) {
                $query->searchBySynonym($request->search);
            }

            // حفظ نسخة للاستخدام في البحث الذكي إذا لزم الأمر
            $queryWithoutSearch = clone $query;

            // الفرز والتصفح
            $sortBy = $request->input('sort_by', 'created_at');
            $sortOrder = $request->input('sort_order', 'desc');
            $query->orderBy($sortBy, $sortOrder);

            $perPage = max(1, (int) $request->get('per_page', 12));
            $brands = $query->paginate($perPage);

            // البحث الذكي (Fallback) عند تمكين البحث وعدم وجود نتائج
            if ($brands->isEmpty() && $request->filled('search')) {
                return $this->handleSmartSearch($queryWithoutSearch, $request, $perPage);
            }

            return api_success(BrandResource::collection($brands), 'تم استرداد العلامات التجارية بنجاح.');
        } catch (Throwable $e) {
            return api_exception($e, 500);
        }
    }

    /**
     * @group 04. نظام المنتجات
     * 
     * إضافة ماركة جديدة
     * 
     * @bodyParam name string required اسم العلامة التجارية. Example: آبل
     * @bodyParam desc string وصف الماركة. Example: شركة تقنية عالمية
     */
    public function store(StoreBrandRequest $request): JsonResponse
    {
        try {
            /** @var \App\Models\User $authUser */
            $authUser = Auth::user();
            $companyId = $authUser->company_id ?? null;

            if (!$authUser || !$companyId) {
                return api_unauthorized('يتطلب المصادقة أو الارتباط بالشركة.');
            }

            // صلاحيات إنشاء ماركة
            if (!$authUser->hasPermissionTo(perm_key('admin.super')) && !$authUser->hasPermissionTo(perm_key('brands.create')) && !$authUser->hasPermissionTo(perm_key('admin.company'))) {
                return api_forbidden('ليس لديك إذن لإنشاء علامات تجارية.');
            }

            DB::beginTransaction();
            try {
                $validatedData = $request->validated();
                $name = $validatedData['name'];
                $slug = \Illuminate\Support\Str::slug($name);

                // 1. الخطة الذكية: البحث عن علامة تجارية عالمية موجودة بنفس الـ slug أو الاسم/المرادفات
                $existing = Brand::whereNull('company_id')
                    ->where(function ($q) use ($name, $slug) {
                        $q->where('slug', $slug)
                            ->orWhere('name', 'LIKE', $name)
                            ->orWhereJsonContains('synonyms', strtolower($name));
                    })->first();

                if ($existing) {
                    // إذا وجدت علامة تجارية عالمية مطابقة، نستخدمها بدلاً من إنشاء واحدة جديدة
                    DB::rollBack();
                    return api_success(new BrandResource($existing), 'تم العثور على علامة تجارية مطابقة موجودة بالفعل.', 200);
                }

                // إذا لم يوجد، نكمل عملية الإنشاء
                $brandCompanyId = ($authUser->hasPermissionTo(perm_key('admin.super')) && isset($validatedData['company_id']))
                    ? $validatedData['company_id']
                    : $companyId;

                $validatedData['company_id'] = $brandCompanyId;
                $validatedData['created_by'] = $authUser->id;
                $validatedData['slug'] = $slug;

                $brand = Brand::create($validatedData);

                if (!empty($validatedData['image_id'])) {
                    ImageService::attachImagesToModel([$validatedData['image_id']], $brand, 'logo');
                }

                $brand->load($this->relations);
                DB::commit();
                return api_success(new BrandResource($brand), 'تم إنشاء العلامة التجارية بنجاح.');
            } catch (ValidationException $e) {
                DB::rollBack();
                return api_error('فشل التحقق من صحة البيانات أثناء تخزين العلامة التجارية.', $e->errors(), 422);
            } catch (Throwable $e) {
                DB::rollBack();
                Log::error($e);
                return api_error('حدث خطأ أثناء حفظ العلامة التجارية.', [], 500);
            }
        } catch (Throwable $e) {
            return api_exception($e, 500);
        }
    }

    /**
     * @group 04. نظام المنتجات
     * 
     * عرض ماركة محددة
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

            $brand = Brand::with($this->relations)->findOrFail($id);

            // التحقق من صلاحيات العرض
            $canView = false;
            if ($authUser->hasPermissionTo(perm_key('admin.super'))) {
                $canView = true;
            } elseif ($authUser->hasAnyPermission([perm_key('brands.view_all'), perm_key('admin.company')])) {
                $canView = $brand->belongsToCurrentCompany();
            } elseif ($authUser->hasPermissionTo(perm_key('brands.view_children'))) {
                $canView = $brand->belongsToCurrentCompany() && $brand->createdByUserOrChildren();
            } elseif ($authUser->hasPermissionTo(perm_key('brands.view_self'))) {
                $canView = $brand->belongsToCurrentCompany() && $brand->createdByCurrentUser();
            }

            if ($canView) {
                return api_success(new BrandResource($brand), 'تم استرداد العلامة التجارية بنجاح.');
            }

            return api_forbidden('ليس لديك إذن لعرض هذه العلامة التجارية.');
        } catch (Throwable $e) {
            return api_exception($e, 500);
        }
    }

    /**
     * @group 04. نظام المنتجات
     * 
     * تحديث بيانات ماركة
     */
    public function update(UpdateBrandRequest $request, string $id): JsonResponse
    {
        try {
            /** @var \App\Models\User $authUser */
            $authUser = Auth::user();
            $companyId = $authUser->company_id ?? null;

            if (!$authUser || !$companyId) {
                return api_unauthorized('يتطلب المصادقة أو الارتباط بالشركة.');
            }

            // يجب تحميل العلاقات الضرورية للتحقق من الصلاحيات (مثل الشركة والمنشئ)
            $brand = Brand::with(['company', 'creator'])->findOrFail($id);

            // التحقق من صلاحيات التحديث
            $canUpdate = false;
            if ($authUser->hasPermissionTo(perm_key('admin.super'))) {
                $canUpdate = true;
            } elseif ($authUser->hasAnyPermission([perm_key('brands.update_all'), perm_key('admin.company')])) {
                $canUpdate = $brand->belongsToCurrentCompany();
            } elseif ($authUser->hasPermissionTo(perm_key('brands.update_children'))) {
                $canUpdate = $brand->belongsToCurrentCompany() && $brand->createdByUserOrChildren();
            } elseif ($authUser->hasPermissionTo(perm_key('brands.update_self'))) {
                $canUpdate = $brand->belongsToCurrentCompany() && $brand->createdByCurrentUser();
            }

            if (!$canUpdate) {
                return api_forbidden('ليس لديك إذن لتحديث هذه العلامة التجارية.');
            }

            DB::beginTransaction();
            try {
                $validatedData = $request->validated();
                $updatedBy = $authUser->id;

                // إذا كان المستخدم سوبر ادمن ويحدد معرف الشركه، يسمح بذلك. وإلا، استخدم معرف الشركه للماركة.
                $brandCompanyId = ($authUser->hasPermissionTo(perm_key('admin.super')) && isset($validatedData['company_id']))
                    ? $validatedData['company_id']
                    : $brand->company_id;

                // التأكد من أن المستخدم مصرح له بتعديل ماركة لشركة أخرى (فقط سوبر أدمن)
                if ($brandCompanyId != $brand->company_id && !$authUser->hasPermissionTo(perm_key('admin.super'))) {
                    DB::rollBack();
                    return api_forbidden('لا يمكنك تغيير شركة العلامة التجارية ما لم تكن مسؤولاً عامًا.');
                }

                $validatedData['company_id'] = $brandCompanyId; // تحديث company_id في البيانات المصدقة
                $validatedData['updated_by'] = $updatedBy; // من قام بالتعديل

                $brand->update($validatedData);

                if (isset($validatedData['image_id'])) {
                    $newImageIds = $validatedData['image_id'] ? [$validatedData['image_id']] : [];
                    ImageService::syncImagesWithModel($newImageIds, $brand, 'logo');
                }

                $brand->load($this->relations);
                DB::commit();
                return api_success(new BrandResource($brand), 'تم تحديث العلامة التجارية بنجاح.');
            } catch (ValidationException $e) {
                DB::rollBack();
                return api_error('فشل التحقق من صحة البيانات أثناء تحديث العلامة التجارية.', $e->errors(), 422);
            } catch (Throwable $e) {
                DB::rollBack();
                return api_error('حدث خطأ أثناء تحديث العلامة التجارية.', [], 500);
            }
        } catch (Throwable $e) {
            return api_exception($e, 500);
        }
    }

    /**
     * @group 04. نظام المنتجات
     * 
     * حذف ماركة
     */
    public function destroy(Brand $brand): JsonResponse
    {
        try {
            /** @var \App\Models\User $authUser */
            $authUser = Auth::user();
            $companyId = $authUser->company_id ?? null;

            if (!$authUser || !$companyId) {
                return api_unauthorized('يتطلب المصادقة أو الارتباط بالشركة.');
            }

            // يجب تحميل العلاقات الضرورية للتحقق من الصلاحيات (مثل الشركة والمنشئ)
            $brand->load(['company', 'creator']); // تم تحميلها هنا لأنها تمر كنموذج Model Binding

            // التحقق من صلاحيات الحذف
            $canDelete = false;
            if ($authUser->hasPermissionTo(perm_key('admin.super'))) {
                $canDelete = true;
            } elseif ($authUser->hasAnyPermission([perm_key('brands.delete_all'), perm_key('admin.company')])) {
                $canDelete = $brand->belongsToCurrentCompany();
            } elseif ($authUser->hasPermissionTo(perm_key('brands.delete_children'))) {
                $canDelete = $brand->belongsToCurrentCompany() && $brand->createdByUserOrChildren();
            } elseif ($authUser->hasPermissionTo(perm_key('brands.delete_self'))) {
                $canDelete = $brand->belongsToCurrentCompany() && $brand->createdByCurrentUser();
            }

            if (!$canDelete) {
                return api_forbidden('ليس لديك إذن لحذف هذه العلامة التجارية.');
            }

            DB::beginTransaction();
            try {
                // تحقق من وجود منتجات مرتبطة
                if ($brand->products()->exists()) {
                    DB::rollBack();
                    return api_error('لا يمكن حذف العلامة التجارية. إنها مرتبطة بمنتج واحد أو أكثر.', [], 409);
                }

                // حفظ نسخة من العلامة التجارية قبل حذفها لإرجاعها في الاستجابة
                $deletedBrand = $brand->replicate();
                $deletedBrand->setRelations($brand->getRelations());

                // حذف الصورة المرتبطة
                if ($brand->image) {
                    ImageService::deleteImages([$brand->image->id]);
                }

                $brand->delete();
                DB::commit();
                return api_success(new BrandResource($deletedBrand), 'تم حذف العلامة التجارية بنجاح.');
            } catch (Throwable $e) {
                DB::rollBack();
                return api_error('حدث خطأ أثناء حذف العلامة التجارية.', [], 500);
            }
        } catch (Throwable $e) {
            return api_exception($e, 500);
        }
    }

    /**
     * @group 04. نظام المنتجات
     * 
     * تغيير حالة الماركة (تفعيل/تعطيل)
     */
    public function toggle(string $id): JsonResponse
    {
        try {
            $brand = Brand::findOrFail($id);
            $brand->update(['active' => !$brand->active]);
            return api_success(new BrandResource($brand), 'تم تغيير حالة العلامة التجارية بنجاح.');
        } catch (Throwable $e) {
            return api_exception($e, 500);
        }
    }

    /**
     * معالجة البحث الذكي عند عدم وجود نتائج للماركات
     */
    private function handleSmartSearch($query, $request, $perPage): JsonResponse
    {
        $search = $request->input('search');
        // جلب عينة كبيرة للبحث عن التشابه برمجياً
        $allBrands = $query->select('id', 'name', 'description', 'synonyms', 'company_id')->limit(300)->get();

        $paginated = smart_search_paginated(
            $allBrands,
            $search,
            ['name', 'synonyms'],
            $request->query(),
            null,
            $perPage,
            $request->input('page', 1)
        );

        return api_success(BrandResource::collection($paginated), 'نتائج ذكية مقترحة.');
    }
}
