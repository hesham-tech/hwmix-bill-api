<?php

namespace Modules\Sales\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Modules\Sales\Observers\InvoiceItemObserver;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Traits\Blameable;
use App\Traits\Scopes;
use Modules\Inventory\Models\Product;
use Modules\Inventory\Models\ProductVariant;
use App\Models\Company;
use App\Models\User;
use App\Models\Subscription;
use Modules\Inventory\Models\DigitalProductDelivery;

#[ObservedBy([InvoiceItemObserver::class])]
class InvoiceItem extends Model
{
    use HasFactory, SoftDeletes, Blameable, Scopes, \App\Traits\LogsActivity;

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

    public function invoice()
    {
        return $this->belongsTo(Invoice::class);
    }

    public function unit()
    {
        return $this->belongsTo(\Modules\Inventory\Models\Unit::class, 'unit_id');
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function variant()
    {
        return $this->belongsTo(ProductVariant::class, 'variant_id');
    }

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function service()
    {
        return $this->belongsTo(Service::class);
    }

    public function subscription()
    {
        return $this->belongsTo(Subscription::class);
    }

    public function digitalDeliveries()
    {
        return $this->hasMany(DigitalProductDelivery::class, 'invoice_item_id');
    }
}
