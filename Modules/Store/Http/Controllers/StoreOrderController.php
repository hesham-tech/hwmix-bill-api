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
        $userId = Auth::guard('sanctum')->id();
        if (!$userId) {
            return response()->json([
                'success' => true,
                'message' => 'لا يوجد طلبات للزوار',
                'data' => []
            ]);
        }

        $orders = StoreOrder::with(['subOrders.items', 'subOrders.company'])
            ->where('user_id', $userId)
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
            $userId = Auth::guard('sanctum')->id();
            $validated = $request->validated();
            
            // Handle Guest Checkout
            if (!$userId) {
                if (empty($validated['guest_address'])) {
                    throw new \Exception('بيانات الضيف مطلوبة');
                }
                
                $guestData = $validated['guest_address'];
                $phone = $guestData['phone'];
                $name = $guestData['recipient_name'];
                
                // Try to find user by phone
                $user = \App\Models\User::where('phone', $phone)->first();
                
                if (!$user) {
                    $user = \App\Models\User::create([
                        'name' => $name,
                        'phone' => $phone,
                        'email' => $phone . '@guest.local', // Dummy email
                        'password' => \Illuminate\Support\Facades\Hash::make(\Illuminate\Support\Str::random(12)),
                        'is_staff' => false,
                        'company_id' => 1, // Fallback if necessary
                    ]);
                    $user->assignRole('customer');
                }
                $userId = $user->id;
            }

            // Create address if guest
            if (!empty($validated['guest_address']) && empty($validated['shipping_address_id'])) {
                $address = \Modules\Store\Models\CustomerAddress::create(array_merge(
                    $validated['guest_address'],
                    ['user_id' => $userId, 'label' => 'home']
                ));
                $validated['shipping_address_id'] = $address->id;
            }

            $userModel = \App\Models\User::find($userId);
            $order = $this->orderService->placeOrder($validated, $userModel);

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
        // For guest, they might track order without auth? For now, we only allow if authenticated.
        $userId = Auth::guard('sanctum')->id();
        
        $query = StoreOrder::with(['subOrders.items', 'subOrders.company']);
        
        if ($userId) {
            $query->where('user_id', $userId);
        } else {
            // If they track by order_number directly
            $query->where('order_number', $id);
        }

        // If ID is numeric, search by ID, else by order_number
        if (is_numeric($id) && $userId) {
            $order = $query->findOrFail($id);
        } else {
            $order = $query->where('order_number', $id)->firstOrFail();
        }

        return response()->json([
            'success' => true,
            'message' => 'تم جلب تفاصيل الطلب بنجاح',
            'data' => new StoreOrderResource($order)
        ]);
    }
}
