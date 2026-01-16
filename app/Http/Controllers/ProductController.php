<?php

namespace App\Http\Controllers;

use Throwable;
use App\Models\Stock;
use App\Models\Product;
use Illuminate\Http\Request;
use App\Models\ProductVariant;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use App\Http\Resources\Product\ProductResource;
use App\Http\Requests\Product\StoreProductRequest;
use App\Http\Requests\Product\UpdateProductRequest;
use App\Services\ProductService;

/**
 * Class ProductController
 *
 * تحكم في عمليات المنتجات (عرض، إضافة، تعديل، حذف)
 *
 * @package App\Http\Controllers
 */
class ProductController extends Controller
{
    /**
     * العلاقات الافتراضية المستخدمة مع المنتجات
     * @var array
     */
    protected array $relations = [
        'company',
        'creator',
        'category',
        'brand',
        'variants.stocks.warehouse',
        'variants.attributes.attribute',
        'variants.attributes.attributeValue',
    ];

    protected ProductService $productService;

    public function __construct(ProductService $productService)
    {
        $this->productService = $productService;
    }

    /**
     * @group 03. إدارة المنتجات والمخزون
     * 
     * عرض قائمة المنتجات
     * 
     * استرجاع قائمة شاملة بالمنتجات مع دعم البحث الذكي والتصفية المتقدمة حسب القسم، الماركة، أو الحالة.
     * 
     * @queryParam search string نص البحث (يتم البحث في الاسم، الوصف، الصنف، والماركة). Example: هاتف
     * @queryParam category_id integer فلترة حسب القسم.
     * @queryParam brand_id integer فلترة حسب الماركة.
     * @queryParam active boolean فلترة حسب الحالة (نشط/غير نشط). Example: true
     * @queryParam per_page integer عدد النتائج في الصفحة. Example: 15
     * 
     * @apiResourceCollection App\Http\Resources\Product\ProductResource
     * @apiResourceModel App\Models\Product
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $authUser = Auth::user();
            if (!$authUser) {
                return api_unauthorized('يتطلب المصادقة.');
            }

            $companyId = $authUser->company_id;

            // الصلاحيات
            $permKeys = [
                'super' => perm_key('admin.super'),
                'view_all' => perm_key('products.view_all'),
                'admin_company' => perm_key('admin.company'),
                'view_children' => perm_key('products.view_children'),
                'view_self' => perm_key('products.view_self'),
            ];

            $baseQuery = Product::with($this->relations);

            if ($authUser->hasPermissionTo($permKeys['super'])) {
                // يرى الجميع
            } elseif ($authUser->hasAnyPermission([$permKeys['view_all'], $permKeys['admin_company']])) {
                $baseQuery->whereCompanyIsCurrent();
            } elseif ($authUser->hasPermissionTo($permKeys['view_children'])) {
                $baseQuery->whereCompanyIsCurrent()->whereCreatedByUserOrChildren();
            } elseif ($authUser->hasPermissionTo($permKeys['view_self'])) {
                $baseQuery->whereCompanyIsCurrent()->whereCreatedByUser();
            } else {
                // الوضع الافتراضي للعملاء: رؤية المنتجات النشطة فقط في شركتهم
                $baseQuery->whereCompanyIsCurrent()->where('active', true);
            }

            // إعدادات عامة
            $perPage = max(1, $request->input('per_page', 20));
            $page = max(1, $request->input('page', 1));
            $sortField = $request->input('sort_by', 'created_at');
            $sortOrder = $request->input('sort_order', 'desc');

            $search = $request->input('search');
            $baseQueryWithoutSearch = clone $baseQuery;

            // فلترة البحث النصي العادي
            if ($request->filled('search')) {
                $baseQuery->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%$search%")
                        ->orWhere('desc', 'like', "%$search%")
                        ->orWhere('slug', 'like', "%$search%")
                        ->orWhereHas(
                            'category',
                            fn($q) =>
                            $q->where('name', 'like', "%$search%")
                                ->orWhere('desc', 'like', "%$search%")
                        )
                        ->orWhereHas(
                            'brand',
                            fn($q) =>
                            $q->where('name', 'like', "%$search%")
                                ->orWhere('desc', 'like', "%$search%")
                        );
                });
            }

            // فلاتر إضافية
            $baseQuery
                ->when($request->filled('category_id'), fn($q) =>
                    $q->where('category_id', $request->input('category_id')))
                ->when($request->filled('brand_id'), fn($q) =>
                    $q->where('brand_id', $request->input('brand_id')))
                ->when($request->filled('active'), fn($q) =>
                    $q->where('active', (bool) $request->input('active')))
                ->when($request->filled('featured'), fn($q) =>
                    $q->where('featured', (bool) $request->input('featured')));

            // الترتيب
            $baseQuery->orderBy($sortField, $sortOrder);

            // النتائج الأساسية
            $products = $baseQuery->paginate($perPage);

            // البحث الذكي لو مفيش نتائج
            if ($products->isEmpty() && $request->filled('search')) {
                $allProducts = (clone $baseQueryWithoutSearch)->limit(300)->get();

                $paginated = smart_search_paginated(
                    $allProducts,
                    $search,
                    ['name', 'desc'],
                    $request->query(),
                    null,
                    $perPage,
                    $page
                );

                return api_success(ProductResource::collection($paginated), 'تم إرجاع نتائج مقترحة بناءً على البحث.');
            }

            if ($products->isEmpty()) {
                return api_success([], 'لم يتم العثور على منتجات.');
            }

            return api_success(ProductResource::collection($products), 'تم جلب المنتجات بنجاح.');
        } catch (Throwable $e) {
            Log::error("فشل جلب المنتجات: " . $e->getMessage(), [
                'exception' => $e,
                'user_id' => $authUser->id ?? null,
                'request_data' => $request->all()
            ]);

            return api_exception($e);
        }
    }


    /**
     * @group 04. نظام المنتجات
     * 
     * إنشاء منتج جديد متكامل
     * 
     * يسمح بإنشاء سجل المنتج الأساسي مع متغيراته (Variants) وسجلات المخزون الأولية في طلب واحد.
     * 
     * @bodyParam name string required اسم المنتج. Example: آيفون 15 بروميجا
     * @bodyParam category_id integer required معرف القسم. Example: 3
     * @bodyParam variants array required مصفوفة المتغيرات (الأحجام، الألوان، إلخ).
     * @bodyParam variants.*.retail_price number سعر البيع القطاعي. Example: 1500
     * @bodyParam variants.*.wholesale_price number سعر الجملة. Example: 1350
     * @bodyParam variants.*.stocks array مصفوفة المخزون لكل مخزن.
     * @bodyParam variants.*.stocks.*.quantity integer الكمية الأولية. Example: 10
     * @bodyParam variants.*.stocks.*.warehouse_id integer معرف المستودع. Example: 1
     */
    public function store(StoreProductRequest $request): JsonResponse
    {
        try {
            /** @var \App\Models\User $authUser */
            $authUser = Auth::user();
            $companyId = $authUser->company_id ?? null;

            if (!$authUser || !$companyId) {
                return api_unauthorized('يجب أن تكون مرتبطًا بشركة.');
            }

            if (!$authUser->hasAnyPermission([perm_key('admin.super'), perm_key('products.create'), perm_key('admin.company')])) {
                return api_forbidden('ليس لديك صلاحية لإنشاء المنتجات.');
            }

            $productData = $request->toSimpleProductData();
            $product = $this->productService->createProduct($productData, $authUser, $companyId);

            return api_success(new ProductResource($product->load($this->relations)), 'تم إنشاء المنتج بنجاح', 201);
        } catch (Throwable $e) {
            return api_exception($e);
        }
    }

    /**
     * @group 04. نظام المنتجات
     * 
     * عرض تفاصيل منتج محدد
     * 
     * جلب بيانات المنتج بالكامل (الاسم، الوصف، المتغيرات، والمخزون المتاح بكل مستودع).
     * 
     * @response 200 {
     *  "success": true,
     *  "data": { "id": 1, "name": "آيفون", "variants": [...] },
     *  "message": "تم جلب بيانات المنتج بنجاح"
     * }
     */
    public function show(Product $product): JsonResponse
    {
        try {
            /** @var \App\Models\User $authUser */
            $authUser = Auth::user();
            $companyId = $authUser->company_id ?? null;

            if (!$authUser || !$companyId) {
                return api_unauthorized('يجب أن تكون مرتبطًا بشركة.');
            }

            // التحقق من صلاحيات العرض
            $canView = false;
            if ($authUser->hasPermissionTo(perm_key('admin.super'))) {
                $canView = true; // المسؤول العام يرى أي منتج
            } elseif ($authUser->hasAnyPermission([perm_key('products.view_all'), perm_key('admin.company')])) {
                // يرى إذا كان المنتج ينتمي للشركة النشطة (بما في ذلك مديرو الشركة)
                $canView = $product->belongsToCurrentCompany();
            } elseif ($authUser->hasPermissionTo(perm_key('products.view_children'))) {
                // يرى إذا كان المنتج أنشأه هو أو أحد التابعين له وتابع للشركة النشطة
                $canView = $product->belongsToCurrentCompany() && $product->createdByUserOrChildren();
            } elseif ($authUser->hasPermissionTo(perm_key('products.view_self'))) {
                // يرى إذا كان المنتج أنشأه هو وتابع للشركة النشطة
                $canView = $product->belongsToCurrentCompany() && $product->createdByCurrentUser();
            }

            if ($canView) {
                $product->load($this->relations); // تحميل العلاقات فقط إذا كان مصرحًا له
                return api_success(ProductResource::make($product), 'تم جلب بيانات المنتج بنجاح');
            }

            return api_forbidden('ليس لديك صلاحية لعرض هذا المنتج.');
        } catch (Throwable $e) {
            return api_exception($e);
        }
    }

    /**
     * @group 04. نظام المنتجات
     * 
     * تحديث بيانات منتج
     * 
     * تعديل بيانات المنتج الأساسية أو تحديث المتغيرات والمخزون المرتبط بها.
     */
    public function update(UpdateProductRequest $request, Product $product): JsonResponse
    {
        try {
            /** @var \App\Models\User $authUser */
            $authUser = Auth::user();

            if (!$authUser || !$authUser->company_id) {
                return api_unauthorized('يجب أن تكون مرتبطًا بشركة.');
            }

            if (!$authUser->hasPermissionTo(perm_key('admin.super')) && !$authUser->hasAnyPermission([perm_key('products.update_all'), perm_key('admin.company')]) && !$product->createdByCurrentUser()) {
                return api_forbidden('ليس لديك صلاحية لتحديث هذا المنتج.');
            }

            $productData = $request->toSimpleProductData();
            $product = $this->productService->updateProduct($product, $productData, $authUser);

            return api_success(new ProductResource($product->load($this->relations)), 'تم تحديث المنتج بنجاح');
        } catch (Throwable $e) {
            return api_exception($e);
        }
    }

    /**
     * @group 04. نظام المنتجات
     * 
     * حذف منتج
     * 
     * حذف المنتج وكافة المتغيرات وسجلات المخزون المرتبطة به بشكل نهائي.
     */
    public function destroy(Product $product): JsonResponse
    {
        try {
            /** @var \App\Models\User $authUser */
            $authUser = Auth::user();
            $companyId = $authUser->company_id ?? null;

            if (!$authUser || !$companyId) {
                return api_unauthorized('يجب أن تكون مرتبطًا بشركة.');
            }

            // التحقق من صلاحيات الحذف
            $canDelete = false;
            if ($authUser->hasPermissionTo(perm_key('admin.super'))) {
                $canDelete = true; // المسؤول العام يمكنه حذف أي منتج
            } elseif ($authUser->hasAnyPermission([perm_key('products.delete_all'), perm_key('admin.company')])) {
                // يمكنه حذف أي منتج داخل الشركة النشطة (بما في ذلك مديرو الشركة)
                $canDelete = $product->belongsToCurrentCompany();
            } elseif ($authUser->hasPermissionTo(perm_key('products.view_children'))) {
                // يمكنه حذف المنتجات التي أنشأها هو أو أحد التابعين له وتابعة للشركة النشطة
                $canDelete = $product->belongsToCurrentCompany() && $product->createdByUserOrChildren();
            } elseif ($authUser->hasPermissionTo(perm_key('products.view_self'))) {
                // يمكنه حذف منتجه الخاص الذي أنشأه وتابع للشركة النشطة
                $canDelete = $product->belongsToCurrentCompany() && $product->createdByCurrentUser();
            }

            if (!$canDelete) {
                return api_forbidden('ليس لديك صلاحية لحذف هذا المنتج.');
            }

            DB::beginTransaction();
            try {
                // حفظ نسخة من المنتج قبل حذفه لإرجاعها في الاستجابة
                $deletedProduct = $product->replicate();
                $deletedProduct->setRelations($product->getRelations()); // نسخ العلاقات المحملة

                // حذف المتغيرات المتعلقة، والتي بدورها ستحذف سجلات المخزون والخصائص
                // يجب التأكد من ضبط cascade deletes في قاعدة البيانات أو حذفها يدوياً بترتيب صحيح
                foreach ($product->variants as $variant) {
                    $variant->attributes()->delete();
                    $variant->stocks()->delete();
                    $variant->delete();
                }
                $product->delete();

                DB::commit();
                return api_success(ProductResource::make($deletedProduct), 'تم حذف المنتج بنجاح');
            } catch (Throwable $e) {
                DB::rollBack();
                return api_error('حدث خطأ أثناء حذف المنتج.', [], 500);
            }
        } catch (Throwable $e) {
            return api_exception($e);
        }
    }
}
