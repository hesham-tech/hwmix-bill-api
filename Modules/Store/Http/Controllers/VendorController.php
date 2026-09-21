<?php

namespace Modules\Store\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use App\Models\Company;
use Modules\Store\Transformers\VendorResource;

class VendorController extends Controller
{
    /**
     * عرض قائمة الشركات المتاحة في المتجر
     */
    public function index(Request $request)
    {
        $vendors = Company::storePublishEnabled()
            ->paginate($request->get('per_page', 15));

        return response()->json([
            'success' => true,
            'message' => 'تم جلب الشركات بنجاح',
            'data' => VendorResource::collection($vendors)->response()->getData(true)
        ]);
    }

    /**
     * عرض بيانات شركة معينة
     */
    public function show($id)
    {
        $vendor = Company::storePublishEnabled()->find($id);

        if (!$vendor) {
            return response()->json([
                'success' => false,
                'message' => 'الشركة غير موجودة',
                'data' => null
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'تم جلب بيانات الشركة بنجاح',
            'data' => new VendorResource($vendor)
        ]);
    }

    public function products(Request $request, $id, \Modules\Store\Services\StoreProductQueryService $queryService)
    {
        $vendor = Company::storePublishEnabled()->find($id);

        if (!$vendor) {
            return response()->json([
                'success' => false,
                'message' => 'الشركة غير موجودة',
                'data' => null
            ], 404);
        }

        $products = $queryService->getVendorProducts($id, $request->all());

        return response()->json([
            'success' => true,
            'message' => 'تم جلب منتجات الشركة بنجاح',
            'data' => \Modules\Store\Transformers\StoreProductResource::collection($products)->response()->getData(true)
        ]);
    }
}
