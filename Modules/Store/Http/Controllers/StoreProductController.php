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
        $request->merge(['price_min'=>$request->input('min_price',$request->input('price_min')),'price_max'=>$request->input('max_price',$request->input('price_max')),
            'vendor_id'=>$request->input('vendor_id',$request->input('company_id')),'in_stock'=>filter_var($request->input('in_stock',false),FILTER_VALIDATE_BOOLEAN,FILTER_NULL_ON_FAILURE)??'invalid']);
        $filters = $request->validate(['category_id'=>'nullable|integer|min:1','brand_id'=>'nullable|integer|min:1','vendor_id'=>'nullable|integer|min:1',
            'search'=>'nullable|string|max:200','in_stock'=>'boolean','price_min'=>'nullable|numeric|min:0|max:9999999999','price_max'=>'nullable|numeric|min:0|max:9999999999|gte:price_min',
            'sort'=>'nullable|in:newest,price_asc,price_desc,best_selling','ids'=>'nullable|array|max:100','ids.*'=>'integer|min:1|distinct','per_page'=>'nullable|integer|min:1|max:48']);
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

    public function validateCart(Request $request) {
        $data=$request->validate(['items'=>'required|array|min:1|max:100','items.*.variant_id'=>'required|integer|min:1|distinct',
            'items.*.product_id'=>'nullable|integer|min:1','items.*.quantity'=>'required|integer|min:1|max:9999']);
        $errors=$this->productQueryService->validateCartItems($data['items']);
        return response()->json(['success'=>true,'data'=>['valid'=>!$errors,'errors'=>$errors]]);
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
