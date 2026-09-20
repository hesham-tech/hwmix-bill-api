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
    ];

    protected $casts = [
        'subtotal'      => 'decimal:2',
        'shipping_cost' => 'decimal:2',
    ];

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
