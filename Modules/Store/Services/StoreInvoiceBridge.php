<?php
namespace Modules\Store\Services;
use App\Support\Money;
use App\Contracts\FinancialEngineInterface;
use Illuminate\Support\Facades\DB;
use Modules\Sales\Models\{Invoice,InvoiceItem,InvoiceType};
use Modules\Store\Models\{StoreSubOrder,StoreStockAllocation,StoreShipment};

class StoreInvoiceBridge
{
    public function invoiceType(int $companyId): ?InvoiceType {
        return InvoiceType::withoutGlobalScopes()->whereIn('code',['sale','sales_invoice','sales'])
            ->where(function($q) use($companyId) {
                $q->where('company_id',$companyId)->orWhereHas('companies',fn($company)=>$company->withoutGlobalScopes()
                    ->where('companies.id',$companyId)->where('company_invoice_type.is_active',true));
            })->orderByRaw("CASE WHEN code = 'sale' THEN 0 ELSE 1 END")->first();
    }
    public function create(int $subOrderId): string {
        return DB::transaction(function() use($subOrderId) {
            $sub=StoreSubOrder::with(['order','items'])->lockForUpdate()->findOrFail($subOrderId);
            if($sub->invoice_id) return 'completed';
            if($sub->status!=='delivered'||!$sub->collected_at) return 'manual_review';
            $type=$this->invoiceType($sub->company_id);
            if(!$type||!$sub->order->customer_user_id) return 'manual_review';
            $shipment=StoreShipment::where('store_sub_order_id',$sub->id)->firstOrFail();
            $warehouse=\Modules\Inventory\Models\Warehouse::withoutGlobalScopes()->find($shipment->quote_snapshot['warehouse_id']);
            $branchId=$warehouse?->branch_id;
            $shipping=$shipment->quote_snapshot['shipping_fee_beneficiary']==='seller'?Money::minor($sub->shipping_cost):0;
            $businessDate=$sub->delivered_at->copy()->timezone(config('store.business_timezone','Africa/Cairo'))->toDateString();
            $subtotal=Money::minor($sub->subtotal)-Money::minor($sub->discount_total);
            $tax=Money::minor($sub->tax_total); $gross=$subtotal+$tax+$shipping;
            $invoice=Invoice::withoutEvents(fn()=>Invoice::withoutGlobalScopes()->create([
                'invoice_number'=>'STORE-'.$sub->id,'company_id'=>$sub->company_id,'branch_id'=>$branchId,
                'user_id'=>$sub->order->customer_user_id,'invoice_type_id'=>$type->id,'invoice_type_code'=>$type->code,
                'status'=>Invoice::STATUS_CONFIRMED,'payment_status'=>Invoice::PAYMENT_PAID,
                'gross_amount'=>Money::decimal($subtotal+$shipping),'net_amount'=>Money::decimal($gross),
                'paid_amount'=>Money::decimal($gross),'remaining_amount'=>'0.00','total_tax'=>Money::decimal($tax),
                'total_discount'=>Money::decimal(Money::minor($sub->discount_total)),'issue_date'=>$businessDate,'due_date'=>$businessDate,
                'cash_box_id'=>null,'created_by'=>null,'is_marketplace_managed'=>true,'store_sub_order_id'=>$sub->id,'is_aggregated'=>true,
                'notes'=>'طلب متجر '.$sub->order->order_number.' — التحصيل خارج خزائن نظام الإدارة.',
            ]));
            $costTotal='0';
            foreach($sub->items as $item) {
                $allocations=StoreStockAllocation::where('store_order_item_id',$item->id)->where('status','consumed')->get();
                if($allocations->isEmpty()) throw new \LogicException('Delivered store inventory allocations are missing.');
                $cost=$allocations->reduce(fn($carry,$a)=>bcadd($carry,(string)$a->cost_total_snapshot,8),'0');
                $itemCostMinor=Money::minor(bcdiv(bcadd(bcmul($cost,'100',8),'0.5',8),'1',0),0);
                $costTotal=bcadd($costTotal,(string)$itemCostMinor,0);
                InvoiceItem::withoutEvents(fn()=>InvoiceItem::create([
                    'invoice_id'=>$invoice->id,'product_id'=>$item->product_id,'variant_id'=>$item->product_variant_id,
                    'name'=>$item->product_name_snapshot,'quantity'=>$item->quantity,'unit_price'=>$item->unit_price,
                    'unit_id'=>$item->selling_unit_id,'conversion_factor_snapshot'=>$item->conversion_factor_snapshot,
                    'base_quantity'=>$item->base_quantity,'unit_price_snapshot'=>$item->unit_price,
                    'tax_amount'=>$item->tax_total,'discount'=>0,'subtotal'=>Money::decimal(Money::minor($item->total_price)-Money::minor($item->tax_total)),
                    'total'=>$item->total_price,'cost_price'=>bcdiv($cost,(string)$item->quantity,6),'total_cost'=>Money::decimal($itemCostMinor),
                    'profit_margin'=>Money::decimal(Money::minor($item->total_price)-Money::minor($item->tax_total)-$itemCostMinor),
                    'company_id'=>$sub->company_id,'created_by'=>null,
                ]));
            }
            $commission=(int)$sub->commission_minor;
            if ($shipping) InvoiceItem::withoutEvents(fn()=>InvoiceItem::create([
                'invoice_id'=>$invoice->id,'name'=>'رسوم الشحن','quantity'=>1,'unit_price'=>Money::decimal($shipping),
                'subtotal'=>Money::decimal($shipping),'total'=>Money::decimal($shipping),'cost_price'=>0,'total_cost'=>0,
                'profit_margin'=>Money::decimal($shipping),'company_id'=>$sub->company_id,'created_by'=>null,
                'conversion_factor_snapshot'=>1,'base_quantity'=>1,'unit_price_snapshot'=>Money::decimal($shipping),
            ]));
            $codSeller=$sub->order->payment_method==='cod'&&$shipment->cod_beneficiary==='seller';
            $platformShipping=(int)$sub->financial_policy_snapshot['platform_shipping_due_minor'];
            $subsidy=(int)($sub->financial_policy_snapshot['seller_shipping_subsidy_minor']??0);
            $asset=$codSeller?Money::minor($sub->subtotal)+$tax+Money::minor($sub->shipping_cost)-Money::minor($sub->discount_total):$gross-$commission-$subsidy;
            $lines=[
                ['type'=>'debit','account_type'=>'asset','sub_account_type'=>$codSeller?'courier_receivable':'marketplace_clearing','amount_minor'=>$asset],
                ['type'=>'debit','account_type'=>'expense','sub_account_type'=>'marketplace_commission','amount_minor'=>$commission],
                ['type'=>'credit','account_type'=>'revenue','sub_account_type'=>'marketplace_sales','amount_minor'=>$subtotal+$shipping],
                ['type'=>'credit','account_type'=>'liability','sub_account_type'=>'sales_tax','amount_minor'=>$tax],
            ];
            if($codSeller) $lines[]=['type'=>'credit','account_type'=>'asset','sub_account_type'=>'marketplace_clearing','amount_minor'=>$commission+$platformShipping];
            if($subsidy) $lines[]=['type'=>'debit','account_type'=>'expense','sub_account_type'=>'marketplace_shipping_subsidy','amount_minor'=>$subsidy];
            $ownCarrierCost=$shipment->quote_snapshot['shipping_fee_beneficiary']==='seller'?(int)($shipment->quote_snapshot['carrier_fee_minor']??Money::minor($sub->shipping_cost)):0;
            if($ownCarrierCost) {
                $lines[]=['type'=>'debit','account_type'=>'expense','sub_account_type'=>'shipping','amount_minor'=>$ownCarrierCost];
                $lines[]=['type'=>'credit','account_type'=>'liability','sub_account_type'=>'courier_payable','amount_minor'=>$ownCarrierCost];
            }
            $costMinor=Money::minor($costTotal,0);
            if($costMinor) {
                $lines[]=['type'=>'debit','account_type'=>'expense','sub_account_type'=>'cost_of_goods_sold','amount_minor'=>$costMinor];
                $lines[]=['type'=>'credit','account_type'=>'asset','sub_account_type'=>'inventory','amount_minor'=>$costMinor];
            }
            app(FinancialEngineInterface::class)->postMarketplaceJournal($invoice,$sub->company_id,'marketplace_sale',$lines,[
                'amount'=>Money::decimal($gross),'currency'=>$sub->order->currency,'branch_id'=>$branchId,'store_sub_order_id'=>$sub->id,'entry_date'=>$sub->delivered_at,
            ]);
            $sub->update(['invoice_id'=>$invoice->id]);
            foreach($sub->items as $item) {
                \Modules\Inventory\Models\ProductVariant::withoutGlobalScopes()->whereKey($item->product_variant_id)->increment('sales_count',(int)$item->quantity);
                \Modules\Inventory\Models\Product::withoutGlobalScopes()->whereKey($item->product_id)->increment('sales_count',(int)$item->quantity);
            }
            app(StoreOutboxService::class)->enqueue('stats.daily','stats:invoice:'.$sub->id,['company_id'=>$sub->company_id,'date'=>$businessDate]);
            return 'completed';
        },3);
    }
}
