<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Traits\Blameable;
use App\Traits\LogsActivity;
use App\Traits\RolePermissions;
use App\Traits\Scopes;
/**
 * @mixin IdeHelperBrand
 */
use App\Models\Image;

class Brand extends Model
{
    use HasFactory, Blameable, Scopes;

    protected $fillable = ['company_id', 'created_by', 'name', 'description', 'active'];

    protected $casts = [
        'active' => 'boolean'
    ];

    public function image()
    {
        return $this->morphOne(Image::class, 'imageable');
    }

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function products()
    {
        return $this->hasMany(Product::class);
    }
    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
