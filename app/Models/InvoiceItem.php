<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use App\Observers\InvoiceItemObserver;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Traits\Blameable;
use App\Traits\Scopes;


#[ObservedBy([InvoiceItemObserver::class])]
class InvoiceItem extends Model
{
    use HasFactory, SoftDeletes, Blameable, Scopes, \App\Traits\LogsActivity;

    /**
     * Label for activity logs.
     */
    public function logLabel()
    {
        return "بند فاتورة ({$this->name}) - كمية: {$this->quantity}";
    }

    protected $guarded = [];

    protected $casts = [
        'quantity' => 'integer',
        'unit_price' => 'float',
        'discount' => 'float',
        'tax_rate' => 'decimal:2',
        'tax_amount' => 'decimal:2',
        'subtotal' => 'decimal:2',
        'cost_price' => 'float',
        'total_cost' => 'float',
        'total' => 'float',
        'profit_margin' => 'float',
    ];
    // 🔗 العلاقة مع الفاتورة
    public function invoice()
    {
        return $this->belongsTo(Invoice::class);
    }
    // 🔗 العلاقة مع المنتج الأساسي
    public function product()
    {
        return $this->belongsTo(\Modules\Inventory\Models\Product::class);
    }
    // 🔗 العلاقة مع متغير المنتج
    public function variant()
    {
        return $this->belongsTo(\Modules\Inventory\Models\ProductVariant::class, 'variant_id');
    }
    // 🔗 العلاقة مع الشركة
    public function company()
    {
        return $this->belongsTo(Company::class);
    }
    // 🔗 علاقة المنشئ
    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
    // 🔗 علاقة المعدّل
    public function updater()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    // 🔗 العلاقة مع الخدمة
    public function service()
    {
        return $this->belongsTo(Service::class);
    }
    // 🔗 العلاقة مع الاشتراك
    public function subscription()
    {
        return $this->belongsTo(Subscription::class);
    }
    // 🔗 علاقة تسليم المنتجات الرقمية
    public function digitalDeliveries()
    {
        return $this->hasMany(DigitalProductDelivery::class, 'invoice_item_id');
    }
}
