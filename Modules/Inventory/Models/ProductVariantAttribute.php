<?php

namespace Modules\Inventory\Models;

use App\Traits\Blameable;
use App\Traits\Scopes;
use App\Traits\LogsActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * موديل سمة المتغير (ProductVariantAttribute) - موديول المخازن
 */
class ProductVariantAttribute extends Model
{
    use HasFactory, Blameable, Scopes, LogsActivity;

    protected $fillable = [
        'product_variant_id',
        'attribute_id',
        'attribute_value_id',
        'company_id',
        'created_by'
    ];

    public function logLabel()
    {
        return "سمة متغير ({$this->attribute?->name}: {$this->attributeValue?->name})";
    }

    public function variant()
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
    }

    public function attribute()
    {
        return $this->belongsTo(Attribute::class, 'attribute_id');
    }

    public function attributeValue()
    {
        return $this->belongsTo(AttributeValue::class, 'attribute_value_id');
    }

    protected static function boot()
    {
        parent::boot();

        static::saved(function ($attr) {
            $attr->variant?->updateSearchableText(true);
        });

        static::deleted(function ($attr) {
            $attr->variant?->updateSearchableText(true);
        });
    }
}
