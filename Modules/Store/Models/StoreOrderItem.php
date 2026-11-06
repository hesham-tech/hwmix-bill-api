<?php

namespace Modules\Store\Models;

// موديل عنصر الطلب — يحفظ snapshot كامل لبيانات المنتج وقت الشراء
use App\Models\Company;
use Modules\Inventory\Models\ProductVariant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class StoreOrderItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'store_order_id', 'store_sub_order_id', 'product_variant_id', 'company_id',
        'product_name_snapshot', 'variant_sku_snapshot', 'variant_image_snapshot',
        'unit_price', 'quantity', 'total_price',
        'product_id','selling_unit_id','unit_name_snapshot','conversion_factor_snapshot','base_quantity',
        'return_policy_snapshot','tax_total','discount_total',
    ];

    protected $casts = [
        'unit_price'  => 'decimal:2',
        'quantity'    => 'decimal:6',
        'total_price' => 'decimal:2',
        'tax_total'=>'decimal:2','discount_total'=>'decimal:2','return_policy_snapshot'=>'array',
        'conversion_factor_snapshot'=>'decimal:6','base_quantity'=>'decimal:6',
    ];

    public function order()
    {
        return $this->belongsTo(StoreOrder::class, 'store_order_id');
    }

    public function subOrder()
    {
        return $this->belongsTo(StoreSubOrder::class, 'store_sub_order_id');
    }

    public function variant()
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id')->withoutGlobalScopes();
    }

    public function company()
    {
        return $this->belongsTo(Company::class);
    }
}
