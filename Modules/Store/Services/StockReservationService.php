<?php
namespace Modules\Store\Services;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Inventory\Models\Stock;
use Modules\Store\Models\StoreStockAllocation;
use Modules\Store\Models\StoreSubOrder;

class StockReservationService
{
    public function eligible(int $variantId,int $companyId,int $warehouseId) {
        return Stock::withoutGlobalScopes()->whereNull('deleted_at')->where('variant_id',$variantId)
            ->where('company_id',$companyId)->where('warehouse_id',$warehouseId)->where('status','available')
            ->where(fn($q)=>$q->whereNull('expiry')->orWhereDate('expiry','>=',now('Africa/Cairo')->toDateString()));
    }
    public function available(int $variantId,int $companyId,int $warehouseId): string {
        return (string)$this->eligible($variantId,$companyId,$warehouseId)->selectRaw('COALESCE(SUM(quantity-reserved),0) as available')->value('available');
    }
    public function reserveForSubOrder(StoreSubOrder $subOrder,iterable $items,?int $warehouseId=null): void {
        if(!$warehouseId) throw ValidationException::withMessages(['stock'=>'مخزن تجهيز الطلب غير محدد.']);
        foreach(collect($items)->sortBy('product_variant_id') as $item) {
            $remaining=(string)$item->base_quantity;
            $stocks=$this->eligible($item->product_variant_id,$subOrder->company_id,$warehouseId)->orderBy('id')->lockForUpdate()->get();
            foreach($stocks as $stock) {
                if(bccomp($remaining,'0',6)<=0) break;
                $available=bcsub($stock->quantity,$stock->reserved,6);
                if(bccomp($available,'0',6)<=0) continue;
                $take=bccomp($available,$remaining,6)<0?$available:$remaining;
                $stock->reserved=bcadd($stock->reserved,$take,6);
                $stock->saveQuietly();
                StoreStockAllocation::create(['store_sub_order_id'=>$subOrder->id,'store_order_item_id'=>$item->id,
                    'stock_id'=>$stock->id,'quantity'=>$take,'status'=>'reserved']);
                $remaining=bcsub($remaining,$take,6);
            }
            if(bccomp($remaining,'0',6)>0) throw ValidationException::withMessages(['items'=>'الكمية المتاحة لأحد المنتجات تغيرت. يرجى مراجعة السلة.']);
        }
    }
    private function finish(StoreSubOrder $subOrder,bool $consume): void {
        $allocations=StoreStockAllocation::where('store_sub_order_id',$subOrder->id)->where('status','reserved')->orderBy('stock_id')->lockForUpdate()->get();
        if($allocations->isEmpty()&&$subOrder->order->checkout_key===null&&$subOrder->items()->exists())
            throw ValidationException::withMessages(['stock'=>'هذا طلب قديم يحتاج مطابقة حجزه قبل تحرير المخزون.']);
        foreach($allocations as $allocation) {
            $stock=Stock::withoutGlobalScopes()->lockForUpdate()->findOrFail($allocation->stock_id);
            if($consume&&($stock->cost===null||!is_numeric($stock->cost)||bccomp((string)$stock->cost,'0',6)<0))
                throw ValidationException::withMessages(['stock'=>'تكلفة دفعة المخزون غير محددة. أكمل التكلفة قبل تأكيد خروج البضاعة لضمان صحة المحاسبة.']);
            if(bccomp($stock->reserved,$allocation->quantity,6)<0||($consume&&bccomp($stock->quantity,$allocation->quantity,6)<0))
                throw ValidationException::withMessages(['stock'=>'يوجد تعارض في المخزون المحجوز يحتاج مراجعة الإدارة.']);
            $stock->reserved=bcsub($stock->reserved,$allocation->quantity,6);
            if($consume) $stock->quantity=bcsub($stock->quantity,$allocation->quantity,6);
            $stock->saveQuietly();
            $changes=['status'=>$consume?'consumed':'released'];
            if ($consume) $changes+=['unit_cost_snapshot'=>(string)$stock->cost,'cost_total_snapshot'=>bcmul((string)$stock->cost,$allocation->quantity,8)];
            $allocation->update($changes);
        }
    }
    public function releaseReservation(StoreSubOrder $subOrder): void { $this->finish($subOrder,false); }
    public function consumeReservation(StoreSubOrder $subOrder): void { $this->finish($subOrder,true); }
}
