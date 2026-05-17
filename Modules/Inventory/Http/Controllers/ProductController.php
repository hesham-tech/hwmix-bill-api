<?php

namespace Modules\Inventory\Http\Controllers;

use App\Http\Controllers\Controller;
use Modules\Inventory\Http\Requests\StoreProductRequest;
use Modules\Inventory\Http\Requests\UpdateProductRequest;
use Modules\Inventory\Http\Resources\ProductResource;
use Modules\Inventory\Models\Product;
use Modules\Inventory\Models\Stock;
use Modules\Inventory\Services\ProductService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * @group إدارة المنتجات (Module Inventory)
 */
class ProductController extends Controller
{
    protected array $indexRelations = ['category', 'brand', 'images', 'variants'];
    protected array $showRelations = [
        'company', 'creator', 'category', 'brand', 'images', 
        'variants.images', 'variants.stocks.warehouse', 
        'variants.attributes.attribute', 'variants.attributes.attributeValue'
    ];

    protected ProductService $productService;

    public function __construct(ProductService $productService)
    {
        $this->productService = $productService;
    }

    /**
     * عرض قائمة المنتجات
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $authUser = Auth::user();
            $query = Product::query();

            // تطبيق الحسابات التجميعية
            $query->withMin('variants', 'retail_price')
                ->withMax('variants', 'retail_price')
                ->addSelect([
                    'total_available_quantity' => Stock::selectRaw('IFNULL(SUM(quantity), 0)')
                        ->join('product_variants', 'stocks.variant_id', '=', 'product_variants.id')
                        ->whereColumn('product_variants.product_id', 'products.id')
                        ->where('stocks.status', '=', 'available')
                ]);

            // تصفية الصلاحيات
            if (!$authUser->hasPermissionTo(perm_key('admin.super'))) {
                $query->whereCompanyIsCurrent();
            }

            // التصفية والبحث
            if ($request->filled('search')) {
                $search = $request->search;
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%$search%")
                        ->orWhere('desc', 'like', "%$search%")
                        ->orWhereHas('category', fn($cq) => $cq->where('name', 'like', "%$search%"))
                        ->orWhereHas('brand', fn($bq) => $bq->where('name', 'like', "%$search%"));
                });
            }

            if ($request->boolean('in_store')) {
                $query->inStore();
            }

            if ($request->boolean('in_sales')) {
                $query->inSales();
            }

            $perPage = max(1, (int) $request->get('per_page', 20));
            $products = $query->with($this->indexRelations)->orderBy('created_at', 'desc')->paginate($perPage);

            return api_success(ProductResource::collection($products), 'تم استرداد المنتجات بنجاح.');
        } catch (Throwable $e) {
            return api_exception($e);
        }
    }

    /**
     * إضافة منتج جديد
     */
    public function store(StoreProductRequest $request): JsonResponse
    {
        try {
            /** @var \App\Models\User $authUser */
            $authUser = Auth::user();
            $companyId = $authUser->company_id;

            $product = $this->productService->createProduct($request->toSimpleProductData(), $authUser, $companyId);

            return api_success(new ProductResource($product->load($this->showRelations)), 'تم إنشاء المنتج بنجاح.', 201);
        } catch (Throwable $e) {
            return api_exception($e);
        }
    }

    /**
     * عرض تفاصيل منتج
     */
    public function show(Product $product): JsonResponse
    {
        try {
            $product->load($this->showRelations);
            return api_success(new ProductResource($product), 'تم استرداد بيانات المنتج بنجاح.');
        } catch (Throwable $e) {
            return api_exception($e);
        }
    }

    /**
     * تحديث بيانات منتج
     */
    public function update(UpdateProductRequest $request, Product $product): JsonResponse
    {
        try {
            /** @var \App\Models\User $authUser */
            $authUser = Auth::user();
            $product = $this->productService->updateProduct($product, $request->toSimpleProductData(), $authUser);

            return api_success(new ProductResource($product->load($this->showRelations)), 'تم تحديث المنتج بنجاح.');
        } catch (Throwable $e) {
            return api_exception($e);
        }
    }

    /**
     * حذف منتج
     */
    public function destroy(Product $product): JsonResponse
    {
        try {
            DB::beginTransaction();
            foreach ($product->variants as $variant) {
                $variant->attributes()->delete();
                $variant->stocks()->delete();
                $variant->delete();
            }
            $product->delete();
            DB::commit();

            return api_success([], 'تم حذف المنتج بنجاح.');
        } catch (Throwable $e) {
            DB::rollBack();
            return api_exception($e);
        }
    }
}
