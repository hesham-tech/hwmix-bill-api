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
        'checkout_key','checkout_fingerprint','tracking_token_hash','shipping_address_snapshot','currency',
        'hold_expires_at','confirmed_at','cancellation_requested_at','policy_snapshot','legal_snapshot','tax_total',
    ];

    protected $casts = [
        'subtotal'       => 'decimal:2',
        'shipping_total' => 'decimal:2',
        'discount_total' => 'decimal:2',
        'total_amount'   => 'decimal:2',
        'tax_total'=>'decimal:2','shipping_address_snapshot'=>'array','policy_snapshot'=>'array','legal_snapshot'=>'array',
        'hold_expires_at'=>'datetime','confirmed_at'=>'datetime','cancellation_requested_at'=>'datetime',
    ];

    public function customer()
    {
        return $this->belongsTo(User::class, 'customer_user_id');
    }

    public function markAsPaid($transaction): void
    {
        app(\Modules\Store\Services\StorePaymentService::class)->confirmOnlinePayment($this, $transaction);
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
    public function refunds() { return $this->hasMany(StoreRefund::class); }

    public function scopeForCustomer($query, $userId)
    {
        return $query->where('customer_user_id', $userId);
    }
}
