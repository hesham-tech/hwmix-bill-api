<?php

namespace App\Models;

use App\Traits\Scopes;
use App\Traits\Blameable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @mixin IdeHelperAttributeValue
 */
class AttributeValue extends Model
{
    use HasFactory, Blameable, Scopes, SoftDeletes, \App\Traits\LogsActivity;

    protected $fillable = [
        'attribute_id',
        'company_id',
        'created_by',
        'name',
        'color',
    ];

    /**
     * Label for activity logs.
     */
    public function logLabel()
    {
        return "قيمة السمة ({$this->name})";
    }

    public function attribute()
    {
        return $this->belongsTo(Attribute::class);
    }
    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
    public function company()
    {
        return $this->belongsTo(Company::class);
    }
    public function variantAttributes()
    {
        return $this->hasMany(ProductVariantAttribute::class, 'attribute_value_id');
    }
}
