<?php
// كلاس يمثل سجل أسعار وتكاليف بيع وشراء الوحدات المختلفة لمتغيرات المنتجات
namespace Modules\Inventory\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Traits\Scopes;

class ProductVariantUnitPrice extends Model
{
    use Scopes;

    protected $fillable = [
        'product_variant_id',
        'unit_id',
        'price',
        'cost',
        'effective_from',
        'effective_to',
        'is_default',
    ];

    protected $casts = [
        'price' => 'float',
        'cost' => 'float',
        'effective_from' => 'date',
        'effective_to' => 'date',
        'is_default' => 'boolean',
    ];

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class, 'unit_id');
    }

    public function logLabel()
    {
        return "سعر وحدة المتغير ({$this->variant?->sku} - {$this->unit?->name} - {$this->price})";
    }
}
