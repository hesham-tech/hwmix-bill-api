<?php

namespace Modules\Inventory\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Traits\Blameable;
use App\Traits\Scopes;
use App\Traits\LogsActivity;
use App\Models\Company;
use App\Models\User;

/**
 * موديل السمة (Attribute) - موديول المخازن
 */
class Attribute extends Model
{
    use HasFactory, Blameable, Scopes, LogsActivity;

    protected $fillable = ['name', 'value', 'active', 'company_id', 'created_by'];

    public function logLabel()
    {
        return "السمة ({$this->name})";
    }

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function values()
    {
        return $this->hasMany(AttributeValue::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function productVariants()
    {
        return $this->belongsToMany(
            ProductVariant::class,
            'product_variant_attributes',
            'attribute_id',
            'product_variant_id'
        )->withPivot(['attribute_value_id', 'company_id', 'created_by'])
            ->withTimestamps();
    }
}
