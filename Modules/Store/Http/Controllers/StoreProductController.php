<?php

namespace Modules\Store\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\Store\Services\StoreProductQueryService;
use Modules\Store\Transformers\StoreProductResource;
use Modules\Store\Transformers\StoreProductDetailResource;

class StoreProductController extends Controller
{
    /**
     * @var StoreProductQueryService
     */
    protected $productQueryService;

    public function __construct(StoreProductQueryService $productQueryService)
    {
        $this->productQueryService = $productQueryService;
    }

    public function index(Request $request)
    {
        $filters = $request->only(['category_id', 'search', 'company_id', 'vendor_id', 'in_stock', 'price_min', 'price_max', 'sort', 'ids', 'per_page']);
        if ($request->has('search')) {
            $filters['q'] = $request->search;
        }
        $products = $this->productQueryService->getProducts($filters);

        return response()->json([
            'success' => true,
            'message' => 'تم جلب المنتجات بنجاح',
            'data' => StoreProductResource::collection($products)->response()->getData(true)
        ]);
    }

    public function featured(Request $request)
    {
        $products = $this->productQueryService->getProducts(['featured' => true]);

        return response()->json([
            'success' => true,
            'message' => 'تم جلب المنتجات المميزة بنجاح',
            'data' => StoreProductResource::collection($products)->response()->getData(true)
        ]);
    }

    public function show($id)
    {
        $product = $this->productQueryService->getProductById($id);

        if (!$product) {
            return response()->json([
                'success' => false,
                'message' => 'المنتج غير موجود أو غير متاح',
                'data' => null
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'تم جلب بيانات المنتج بنجاح',
            'data' => new StoreProductDetailResource($product)
        ]);
    }

    public function categories()
    {
        $categories = $this->productQueryService->getCategories();
        return response()->json([
            'success' => true,
            'data' => \Modules\Inventory\Http\Resources\CategoryResource::collection($categories)
        ]);
    }

    public function brands()
    {
        $brands = $this->productQueryService->getBrands();
        return response()->json([
            'success' => true,
            'data' => $brands
        ]);
    }
}
