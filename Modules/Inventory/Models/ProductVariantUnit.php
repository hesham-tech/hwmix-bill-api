<?php
// كلاس يمثل ارتباط متغيرات المنتجات بوحدات البيع الفرعية ومعاملات التحويل للوحدة الأساسية
namespace Modules\Inventory\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Traits\Scopes;

class ProductVariantUnit extends Model
{
    use Scopes;

    protected $fillable = [
        'product_variant_id',
        'unit_id',
        'conversion_factor_to_base',
        'is_default',
        'min_quantity',
        'max_quantity',
        'allow_fraction',
    ];

    protected $casts = [
        'conversion_factor_to_base' => 'float',
        'is_default' => 'boolean',
        'min_quantity' => 'float',
        'max_quantity' => 'float',
        'allow_fraction' => 'boolean',
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
        return "وحدة المتغير ({$this->variant?->sku} - {$this->unit?->name})";
    }
}
