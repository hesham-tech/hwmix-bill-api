<?php

namespace Modules\Store\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\Store\Services\StoreOrderService;
use Modules\Store\Http\Requests\PlaceOrderRequest;
use Modules\Store\Transformers\StoreOrderResource;
use Modules\Store\Models\StoreOrder;
use Illuminate\Support\Facades\Auth;

class StoreOrderController extends Controller
{
    /**
     * @var StoreOrderService
     */
    protected $orderService;

    public function __construct(StoreOrderService $orderService)
    {
        $this->orderService = $orderService;
    }

    /**
     * عرض طلبات العميل
     */
    public function index(Request $request)
    {
        $orders = StoreOrder::with(['subOrders.items', 'subOrders.company'])
            ->where('user_id', Auth::id())
            ->orderBy('created_at', 'desc')
            ->paginate($request->get('per_page', 15));

        return response()->json([
            'success' => true,
            'message' => 'تم جلب الطلبات بنجاح',
            'data' => StoreOrderResource::collection($orders)->response()->getData(true)
        ]);
    }

    /**
     * إنشاء طلب جديد (Checkout)
     */
    public function store(PlaceOrderRequest $request)
    {
        try {
            $order = $this->orderService->placeOrder(Auth::id(), $request->validated());

            return response()->json([
                'success' => true,
                'message' => 'تم إنشاء الطلب بنجاح',
                'data' => new StoreOrderResource($order->load(['subOrders.items', 'subOrders.company']))
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'حدث خطأ أثناء إنشاء الطلب: ' . $e->getMessage(),
                'data' => null
            ], 400);
        }
    }

    /**
     * عرض تفاصيل طلب معين للعميل
     */
    public function show($id)
    {
        $order = StoreOrder::with(['subOrders.items', 'subOrders.company'])
            ->where('user_id', Auth::id())
            ->findOrFail($id);

        return response()->json([
            'success' => true,
            'message' => 'تم جلب تفاصيل الطلب بنجاح',
            'data' => new StoreOrderResource($order)
        ]);
    }
}
