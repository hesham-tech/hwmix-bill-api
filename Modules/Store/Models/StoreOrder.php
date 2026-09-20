<?php

namespace Modules\Store\Models;

// موديل الطلب الرئيسي للمتجر — يجمع طلبات من شركات متعددة في طلب واحد
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class StoreOrder extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'order_number', 'customer_user_id', 'shipping_address_id',
        'status', 'payment_method', 'payment_status',
        'subtotal', 'shipping_total', 'discount_total', 'total_amount',
        'customer_notes',
    ];

    protected $casts = [
        'subtotal'       => 'decimal:2',
        'shipping_total' => 'decimal:2',
        'discount_total' => 'decimal:2',
        'total_amount'   => 'decimal:2',
    ];

    public function customer()
    {
        return $this->belongsTo(User::class, 'customer_user_id');
    }

    public function shippingAddress()
    {
        return $this->belongsTo(CustomerAddress::class, 'shipping_address_id');
    }

    public function subOrders()
    {
        return $this->hasMany(StoreSubOrder::class);
    }

    public function items()
    {
        return $this->hasMany(StoreOrderItem::class);
    }

    public function scopeForCustomer($query, $userId)
    {
        return $query->where('customer_user_id', $userId);
    }
}
