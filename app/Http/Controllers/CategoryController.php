<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Http\Requests\Category\StoreCategoryRequest;
use App\Http\Requests\Category\UpdateCategoryRequest;
use App\Http\Resources\Category\CategoryResource;
use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use App\Services\ImageService;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Log;
use Throwable;

class CategoryController extends Controller
{
    protected array $relations;

    public function __construct()
    {
        $this->relations = [
            'children.children', // لتحميل الفئات الفرعية المتداخلة
            'parent',            // الفئة الأم
            'company',           // للتحقق من belongsToCurrentCompany
            'creator',           // للتحقق من createdByCurrentUser/OrChildren
            'products',          // للتحقق من المنتجات المرتبطة قبل الحذف
            'image',             // تحميل الصورة المرتبطة
        ];
    }

    /**
     * @group 03. إدارة المنتجات والمخزون
     * 
     * عرض قائمة الأقسام (الفئات)
     * 
     * استرجاع شجرة الأقسام بالكامل (الأقسام الرئيسية والفرعية) مع دعم البحث والفلترة.
     * 
     * @queryParam search string البحث باسم القسم. Example: هواتف
     * 
     * @apiResourceCollection App\Http\Resources\Category\CategoryResource
     * @apiResourceModel App\Models\Category
     */
    public function index(Request $request): JsonResponse
    {
        try {
            /** @var \App\Models\User $authUser */
            $authUser = Auth::user();
            $query = Category::with($this->relations);

            // Filter by parent_id (default to null if not searching)
            if (!$request->filled('search')) {
                $parentId = $request->input('parent_id');
                if ($parentId === 'null' || $parentId === null) {
                    $query->whereNull('parent_id');
                } else {
                    $query->where('parent_id', $parentId);
                }
            }
            $companyId = $authUser->company_id ?? null;

            // تطبيق منطق الصلاحيات + تضمين الفئات العالمية
            if ($authUser->hasPermissionTo(perm_key('admin.super'))) {
                // المسؤول العام يرى كل شيء
            } else {
                // المستخدم العادي يرى فئات شركته + الفئات العالمية (NULL company_id)
                $query->where(function ($q) use ($authUser) {
                    $q->whereCompanyIsCurrent()
                        ->orWhereNull('company_id');
                });
            }

            // التحقق من وجود قيمة البحث باستخدام الـ scope الجديد
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
            $categories = $query->paginate($perPage);

            // البحث الذكي (Fallback) عند تمكين البحث وعدم وجود نتائج
            if ($categories->isEmpty() && $request->filled('search')) {
                return $this->handleSmartSearch($queryWithoutSearch, $request, $perPage);
            }

            return api_success(CategoryResource::collection($categories), 'تم استرداد الفئات بنجاح.');
        } catch (Throwable $e) {
            return api_exception($e, 500);
        }
    }

    /**
     * @group 03. إدارة المنتجات والمخزون
     * 
     * إضافة قسم جديد
     * 
     * @bodyParam name string required اسم القسم. Example: إلكترونيات
     * @bodyParam parent_id integer معرف القسم الأب (اختياري). Example: 1
     */
    public function store(StoreCategoryRequest $request): JsonResponse
    {
        try {
            /** @var \App\Models\User $authUser */
            $authUser = Auth::user();
            $companyId = $authUser->company_id ?? null;

            if (!$authUser || !$companyId) {
                return api_unauthorized('يتطلب المصادقة أو الارتباط بالشركة.');
            }

            // صلاحيات إنشاء فئة
            if (!$authUser->hasPermissionTo(perm_key('admin.super')) && !$authUser->hasPermissionTo(perm_key('categories.create')) && !$authUser->hasPermissionTo(perm_key('admin.company'))) {
                return api_forbidden('ليس لديك إذن لإنشاء فئات.');
            }

            DB::beginTransaction();
            try {
                $validatedData = $request->validated();
                $name = $validatedData['name'];
                $slug = \Illuminate\Support\Str::slug($name);

                // 1. الخطة الذكية: البحث عن فئة عالمية موجودة بنفس الـ slug أو الاسم/المرادفات
                $existing = Category::whereNull('company_id')
                    ->where(function ($q) use ($name, $slug) {
                        $q->where('slug', $slug)
                            ->orWhere('name', 'LIKE', $name)
                            ->orWhereJsonContains('synonyms', strtolower($name));
                    })->first();

                if ($existing) {
                    // إذا وجدنا فئة عالمية مطابقة، نستخدمها بدلاً من إنشاء واحدة جديدة
                    DB::rollBack();
                    return api_success(new CategoryResource($existing), 'تم العثور على فئة مطابقة موجودة بالفعل.', 200);
                }

                // إذا لم يوجد، نكمل عملية الإنشاء
                // إذا كان المستخدم super_admin ويحدد company_id، يظل السجل خاصاً بالشركة أو NULL إذا لم يحدد
                $categoryCompanyId = ($authUser->hasPermissionTo(perm_key('admin.super')) && isset($validatedData['company_id']))
                    ? $validatedData['company_id']
                    : $companyId;

                $validatedData['company_id'] = $categoryCompanyId;
                $validatedData['created_by'] = $authUser->id;
                $validatedData['slug'] = $slug;

                $category = Category::create($validatedData);

                if (!empty($validatedData['image_id'])) {
                    ImageService::attachImagesToModel([$validatedData['image_id']], $category, 'logo');
                }

                $category->load($this->relations);
                DB::commit();
                return api_success(new CategoryResource($category), 'تم إنشاء الفئة بنجاح.', 201);
            } catch (ValidationException $e) {
                DB::rollBack();
                return api_error('فشل التحقق من صحة البيانات أثناء تخزين الفئة.', $e->errors(), 422);
            } catch (Throwable $e) {
                DB::rollBack();
                Log::error($e);
                return api_error('حدث خطأ أثناء حفظ الفئة.', [], 500);
            }
        } catch (Throwable $e) {
            return api_exception($e, 500);
        }
    }

    /**
     * @group 04. نظام المنتجات
     * 
     * عرض تفاصيل قسم
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

            $category = Category::with($this->relations)->findOrFail($id);

            // التحقق من صلاحيات العرض
            $canView = false;
            if ($authUser->hasPermissionTo(perm_key('admin.super'))) {
                $canView = true;
            } elseif ($authUser->hasAnyPermission([perm_key('categories.view_all'), perm_key('admin.company')])) {
                $canView = $category->belongsToCurrentCompany();
            } elseif ($authUser->hasPermissionTo(perm_key('categories.view_children'))) {
                $canView = $category->belongsToCurrentCompany() && $category->createdByUserOrChildren();
            } elseif ($authUser->hasPermissionTo(perm_key('categories.view_self'))) {
                $canView = $category->belongsToCurrentCompany() && $category->createdByCurrentUser();
            }

            if ($canView) {
                return api_success(new CategoryResource($category), 'تم استرداد الفئة بنجاح.');
            }

            return api_forbidden('ليس لديك إذن لعرض هذه الفئة.');
        } catch (Throwable $e) {
            return api_exception($e, 500);
        }
    }

    /**
     * @group 04. نظام المنتجات
     * 
     * تحديث بيانات قسم
     */
    public function update(UpdateCategoryRequest $request, string $id): JsonResponse
    {
        try {
            /** @var \App\Models\User $authUser */
            $authUser = Auth::user();
            $companyId = $authUser->company_id ?? null;

            if (!$authUser || !$companyId) {
                return api_unauthorized('يتطلب المصادقة أو الارتباط بالشركة.');
            }

            $category = Category::with(['company', 'creator'])->findOrFail($id);

            // التحقق من صلاحيات التحديث
            $canUpdate = false;
            if ($authUser->hasPermissionTo(perm_key('admin.super'))) {
                $canUpdate = true;
            } elseif ($authUser->hasAnyPermission([perm_key('categories.update_all'), perm_key('admin.company')])) {
                $canUpdate = $category->belongsToCurrentCompany();
            } elseif ($authUser->hasPermissionTo(perm_key('categories.update_children'))) {
                $canUpdate = $category->belongsToCurrentCompany() && $category->createdByUserOrChildren();
            } elseif ($authUser->hasPermissionTo(perm_key('categories.update_self'))) {
                $canUpdate = $category->belongsToCurrentCompany() && $category->createdByCurrentUser();
            }

            if (!$canUpdate) {
                return api_forbidden('ليس لديك إذن لتحديث هذه الفئة.');
            }

            DB::beginTransaction();
            try {
                $validatedData = $request->validated();
                $updatedBy = $authUser->id;

                // إذا كان المستخدم سوبر ادمن ويحدد معرف الشركه، يسمح بذلك. وإلا، استخدم معرف الشركه للفئة.
                $categoryCompanyId = ($authUser->hasPermissionTo(perm_key('admin.super')) && isset($validatedData['company_id']))
                    ? $validatedData['company_id']
                    : $category->company_id;

                // التأكد من أن المستخدم مصرح له بتعديل فئة لشركة أخرى (فقط سوبر أدمن)
                if ($categoryCompanyId != $category->company_id && !$authUser->hasPermissionTo(perm_key('admin.super'))) {
                    DB::rollBack();
                    return api_forbidden('لا يمكنك تغيير شركة الفئة ما لم تكن مسؤولاً عامًا.');
                }

                $validatedData['company_id'] = $categoryCompanyId;
                $validatedData['updated_by'] = $updatedBy;

                $category->update($validatedData);

                if (isset($validatedData['image_id'])) {
                    $newImageIds = $validatedData['image_id'] ? [$validatedData['image_id']] : [];
                    ImageService::syncImagesWithModel($newImageIds, $category, 'logo');
                }

                $category->load($this->relations); // تحميل العلاقات بعد التحديث
                DB::commit();
                return api_success(new CategoryResource($category), 'تم تحديث الفئة بنجاح.');
            } catch (ValidationException $e) {
                DB::rollBack();
                return api_error('فشل التحقق من صحة البيانات أثناء تحديث الفئة.', $e->errors(), 422);
            } catch (Throwable $e) {
                DB::rollBack();
                return api_error('حدث خطأ أثناء تحديث الفئة.', [], 500);
            }
        } catch (Throwable $e) {
            return api_exception($e, 500);
        }
    }

    /**
     * @group 04. نظام المنتجات
     * 
     * حذف قسم
     */
    public function destroy(Request $request): JsonResponse
    {
        try {
            $request->validate(['id' => 'required|exists:categories,id']);
            $id = $request->input('id');

            /** @var \App\Models\User $authUser */
            $authUser = Auth::user();
            $companyId = $authUser->company_id ?? null;

            if (!$authUser || !$companyId) {
                return api_unauthorized('يتطلب المصادقة أو الارتباط بالشركة.');
            }

            $category = Category::with(['company', 'creator'])->findOrFail($id);

            // التحقق من صلاحيات الحذف
            $canDelete = false;
            if ($authUser->hasPermissionTo(perm_key('admin.super'))) {
                $canDelete = true;
            } elseif ($authUser->hasAnyPermission([perm_key('categories.delete_all'), perm_key('admin.company')])) {
                $canDelete = $category->belongsToCurrentCompany();
            } elseif ($authUser->hasPermissionTo(perm_key('categories.delete_children'))) {
                $canDelete = $category->belongsToCurrentCompany() && $category->createdByUserOrChildren();
            } elseif ($authUser->hasPermissionTo(perm_key('categories.delete_self'))) {
                $canDelete = $category->belongsToCurrentCompany() && $category->createdByCurrentUser();
            }

            if (!$canDelete) {
                return api_forbidden('ليس لديك إذن لحذف هذه الفئة.');
            }

            DB::beginTransaction();
            try {
                // تحقق مما إذا كانت الفئة مرتبطة بأي منتجات أو فئات فرعية
                if ($category->products()->exists()) { // افتراض أن لديك علاقة products في نموذج Category
                    DB::rollBack();
                    return api_error('لا يمكن حذف الفئة. إنها مرتبطة بمنتج واحد أو أكثر.', [], 409);
                }
                if ($category->children()->exists()) {
                    DB::rollBack();
                    return api_error('لا يمكن حذف الفئة. إنها تحتوي على فئات فرعية.', [], 409);
                }

                // حفظ نسخة من الفئة قبل حذفها لإرجاعها في الاستجابة
                $deletedCategory = $category->replicate();
                $deletedCategory->setRelations($category->getRelations()); // نسخ العلاقات المحملة

                $category->delete();
                DB::commit();
                return api_success(new CategoryResource($deletedCategory), 'تم حذف الفئة بنجاح.');
            } catch (Throwable $e) {
                DB::rollBack();
                return api_error('حدث خطأ أثناء حذف الفئة.', [], 500);
            }
        } catch (Throwable $e) {
            return api_exception($e, 500);
        }
    }

    /**
     * @group 03. إدارة المنتجات والمخزون
     * 
     * تغيير حالة الفئة (تفعيل/تعطيل)
     */
    public function toggle(string $id): JsonResponse
    {
        try {
            $category = Category::findOrFail($id);
            $category->update(['active' => !$category->active]);
            return api_success(new CategoryResource($category), 'تم تغيير حالة الفئة بنجاح.');
        } catch (Throwable $e) {
            return api_exception($e, 500);
        }
    }

    /**
     * @group 03. إدارة المنتجات والمخزون
     * 
     * استرجاع مسار الفئة (Breadcrumbs)
     */
    public function breadcrumbs(string $id): JsonResponse
    {
        try {
            $category = Category::findOrFail($id);
            $breadcrumbs = [];
            $current = $category;

            while ($current) {
                array_unshift($breadcrumbs, [
                    'id' => $current->id,
                    'name' => $current->name,
                ]);
                $current = $current->parent;
            }

            return api_success($breadcrumbs, 'تم استرداد مسار الفئة بنجاح.');
        } catch (Throwable $e) {
            return api_exception($e, 500);
        }
    }

    /**
     * معالجة البحث الذكي عند عدم وجود نتائج للفئات
     */
    private function handleSmartSearch($query, $request, $perPage): JsonResponse
    {
        $search = $request->input('search');
        // جلب عينة كبيرة للبحث عن التشابه برمجياً (300 عنصر كحد أقصى)
        $allCategories = $query->select('id', 'name', 'description', 'synonyms', 'company_id', 'parent_id')->limit(300)->get();

        $paginated = smart_search_paginated(
            $allCategories,
            $search,
            ['name', 'synonyms'],
            $request->query(),
            null,
            $perPage,
            $request->input('page', 1)
        );

        return api_success(CategoryResource::collection($paginated), 'نتائج ذكية مقترحة.');
    }
}
