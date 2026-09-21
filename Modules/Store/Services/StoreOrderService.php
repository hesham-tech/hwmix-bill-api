<?php

namespace Modules\Store\Services;

// خدمة إنشاء وإدارة طلبات المتجر الإلكتروني — تنشئ الطلب وتقسمه على الشركات وتدير حجز المخزون
use App\Models\User;
use App\Models\Company;
use Modules\Store\Models\StoreOrder;
use Modules\Store\Models\StoreSubOrder;
use Modules\Store\Models\StoreOrderItem;
use Modules\Store\Events\OrderPlaced;
use Modules\Store\Events\SubOrderStatusChanged;
use Modules\Inventory\Models\ProductVariant;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class StoreOrderService
{
    public function __construct(
        private StockReservationService $stockReservation
    ) {}

    /**
     * إنشاء طلب جديد في المتجر
     */
    public function placeOrder(array $data, User $customer): StoreOrder
    {
        return DB::transaction(function () use ($data, $customer) {
            Log::info('StoreOrderService: بدء إنشاء طلب جديد', ['customer_id' => $customer->id]);

            // التحقق من المخزون أولاً
            $this->stockReservation->validateAll($data['items']);

            // Calculate exact secure prices from DB
            $secureItems = [];
            $orderSubtotal = 0;
            
            foreach ($data['items'] as $item) {
                $variant = ProductVariant::withoutGlobalScopes()
                    ->with('product')
                    ->findOrFail($item['variant_id']);
                
                $unitPrice = $variant->retail_price ?? 0;
                $totalPrice = $unitPrice * $item['quantity'];
                
                $secureItems[] = [
                    'variant_id' => $variant->id,
                    'company_id' => $variant->product->company_id, // Trust DB company_id over payload
                    'quantity' => $item['quantity'],
                    'unit_price' => $unitPrice,
                    'total_price' => $totalPrice,
                    'variant' => $variant
                ];
                
                $orderSubtotal += $totalPrice;
            }

            // إنشاء الطلب الرئيسي
            $order = StoreOrder::create([
                'order_number'        => $this->generateOrderNumber(),
                'customer_user_id'    => $customer->id,
                'shipping_address_id' => $data['address_id'],
                'payment_method'      => $data['payment_method'] ?? 'cod',
                'payment_status'      => 'unpaid',
                'subtotal'            => $orderSubtotal,
                'total_amount'        => $orderSubtotal,
                'customer_notes'      => $data['notes'] ?? null,
            ]);

            // تجميع العناصر حسب company_id
            $grouped = collect($secureItems)->groupBy('company_id');

            $letter = 'A';
            foreach ($grouped as $companyId => $items) {
                $subOrderSubtotal = $items->sum('total_price');

                $subOrder = StoreSubOrder::create([
                    'store_order_id'   => $order->id,
                    'company_id'       => $companyId,
                    'sub_order_number' => $order->order_number . '-' . $letter,
                    'status'           => 'pending',
                    'subtotal'         => $subOrderSubtotal,
                ]);

                foreach ($items as $item) {
                    $variant = $item['variant'];

                    StoreOrderItem::create([
                        'store_order_id'         => $order->id,
                        'store_sub_order_id'     => $subOrder->id,
                        'product_variant_id'     => $variant->id,
                        'company_id'             => $companyId,
                        'product_name_snapshot'  => $variant->product?->name ?? 'منتج',
                        'variant_sku_snapshot'   => $variant->sku,
                        'variant_image_snapshot' => $variant->image,
                        'unit_price'             => $item['unit_price'],
                        'quantity'               => $item['quantity'],
                        'total_price'            => $item['total_price'],
                    ]);
                }

                // حجز المخزون (We pass the DB-verified array)
                $this->stockReservation->reserveForSubOrder($subOrder, $items->toArray());

                $letter++;
            }

            event(new OrderPlaced($order));

            return $order->load(['subOrders.company', 'subOrders.items', 'shippingAddress']);
        });
    }

    /**
     * إلغاء طلب من قِبَل العميل
     */
    public function cancelOrder(StoreOrder $order): void
    {
        if (!in_array($order->status, ['pending', 'confirmed'])) {
            throw new \Exception('لا يمكن إلغاء هذا الطلب في مرحلته الحالية');
        }

        DB::transaction(function () use ($order) {
            foreach ($order->subOrders as $subOrder) {
                if ($subOrder->status !== 'cancelled') {
                    $this->stockReservation->releaseReservation($subOrder);
                    $subOrder->update(['status' => 'cancelled']);
                }
            }
            $order->update(['status' => 'cancelled']);
        });
    }

    /**
     * تحديث حالة Sub-Order من قِبَل البائع
     */
    public function updateSubOrderStatus(StoreSubOrder $subOrder, string $status, array $extra = []): void
    {
        $subOrder->update(array_merge(['status' => $status], $extra));
        event(new SubOrderStatusChanged($subOrder));
        $this->syncParentOrderStatus($subOrder->order);
    }

    /**
     * مزامنة حالة الطلب الرئيسي بناءً على Sub-Orders
     */
    private function syncParentOrderStatus(StoreOrder $order): void
    {
        $order->load('subOrders');
        $statuses = $order->subOrders->pluck('status')->unique()->toArray();

        $parentStatus = match (true) {
            in_array('cancelled', $statuses) && count($statuses) === 1 => 'cancelled',
            in_array('delivered', $statuses) && count($statuses) === 1 => 'delivered',
            in_array('shipped', $statuses) && in_array('delivered', $statuses) => 'partially_shipped',
            in_array('shipped', $statuses) => 'shipped',
            in_array('processing', $statuses) => 'processing',
            in_array('confirmed', $statuses) => 'confirmed',
            default => 'pending',
        };

        $order->update(['status' => $parentStatus]);
    }

    /**
     * توليد رقم طلب فريد وقابل للقراءة
     */
    private function generateOrderNumber(): string
    {
        $year = now()->format('Y');
        $lastOrder = StoreOrder::withoutGlobalScopes()
            ->where('order_number', 'like', "ORD-{$year}-%")
            ->orderByDesc('id')
            ->first();

        $lastNum = $lastOrder
            ? (int) substr($lastOrder->order_number, -6)
            : 0;

        return sprintf('ORD-%s-%06d', $year, $lastNum + 1);
    }
}
