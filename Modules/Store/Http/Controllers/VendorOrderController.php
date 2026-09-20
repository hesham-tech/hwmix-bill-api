<?php

namespace Modules\Store\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\Store\Models\StoreSubOrder;
use Modules\Store\Transformers\StoreSubOrderResource;
use Modules\Store\Http\Requests\UpdateSubOrderStatusRequest;
use Modules\Store\Events\SubOrderStatusChanged;

class VendorOrderController extends Controller
{
    /**
     * عرض الطلبات الفرعية الخاصة بشركة المستخدم الحالي (البائع)
     */
    public function index(Request $request)
    {
        // افترض أن المستخدم مسجل الدخول ولديه company_id
        $companyId = auth()->user()->company_id;

        $subOrders = StoreSubOrder::with(['items', 'order'])
            ->where('company_id', $companyId)
            ->orderBy('created_at', 'desc')
            ->paginate($request->get('per_page', 15));

        return response()->json([
            'success' => true,
            'message' => 'تم جلب طلبات البائع بنجاح',
            'data' => StoreSubOrderResource::collection($subOrders)->response()->getData(true)
        ]);
    }

    /**
     * عرض تفاصيل طلب فرعي للبائع
     */
    public function show($id)
    {
        $companyId = auth()->user()->company_id;

        $subOrder = StoreSubOrder::with(['items', 'order'])
            ->where('company_id', $companyId)
            ->findOrFail($id);

        return response()->json([
            'success' => true,
            'message' => 'تم جلب تفاصيل الطلب بنجاح',
            'data' => new StoreSubOrderResource($subOrder)
        ]);
    }

    /**
     * تحديث حالة الطلب الفرعي
     */
    public function updateStatus(UpdateSubOrderStatusRequest $request, $id)
    {
        $companyId = auth()->user()->company_id;

        $subOrder = StoreSubOrder::where('company_id', $companyId)->findOrFail($id);
        
        $oldStatus = $subOrder->status;
        $subOrder->status = $request->validated()['status'];
        $subOrder->save();

        if ($oldStatus !== $subOrder->status) {
            event(new SubOrderStatusChanged($subOrder, $oldStatus));
        }

        return response()->json([
            'success' => true,
            'message' => 'تم تحديث حالة الطلب بنجاح',
            'data' => new StoreSubOrderResource($subOrder->load(['items', 'order']))
        ]);
    }
}
