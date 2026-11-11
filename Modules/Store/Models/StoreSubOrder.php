<?php

namespace Modules\Store\Models;

// موديل الطلب الفرعي — يمثل جزء الطلب الخاص بشركة واحدة
use App\Models\Company;
use Modules\Sales\Models\Invoice;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class StoreSubOrder extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'store_order_id', 'company_id', 'invoice_id', 'sub_order_number',
        'status', 'tracking_number', 'shipping_carrier',
        'subtotal', 'shipping_cost', 'vendor_notes',
        'commission_minor','financial_policy_snapshot','seller_ready_at','delivered_at','collected_at','remitted_at',
        'settlement_available_at','commission_recognized_at','settled_at','refunded_minor','refund_requested_at',
        'tax_total','discount_total','seller_reversed_minor','commission_reversed_minor',
    ];

    protected $casts = [
        'subtotal'      => 'decimal:2',
        'shipping_cost' => 'decimal:2',
        'tax_total'=>'decimal:2','discount_total'=>'decimal:2','financial_policy_snapshot'=>'array',
        'commission_minor'=>'integer','refunded_minor'=>'integer','seller_reversed_minor'=>'integer','commission_reversed_minor'=>'integer',
        'seller_ready_at'=>'datetime','delivered_at'=>'datetime','collected_at'=>'datetime','remitted_at'=>'datetime',
        'settlement_available_at'=>'datetime','commission_recognized_at'=>'datetime','settled_at'=>'datetime','refund_requested_at'=>'datetime',
    ];

    public function shipment() {return $this->hasOne(StoreShipment::class);}

    public function order()
    {
        return $this->belongsTo(StoreOrder::class, 'store_order_id');
    }

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function invoice()
    {
        return $this->belongsTo(Invoice::class);
    }

    public function items()
    {
        return $this->hasMany(StoreOrderItem::class);
    }

    public function scopeForCompany($query, $companyId)
    {
        return $query->where('company_id', $companyId);
    }
}
