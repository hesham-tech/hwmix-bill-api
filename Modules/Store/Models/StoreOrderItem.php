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
    ];

    protected $casts = [
        'unit_price'  => 'decimal:2',
        'quantity'    => 'decimal:6',
        'total_price' => 'decimal:2',
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
