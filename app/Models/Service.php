<?php

namespace App\Models;

use App\Traits\Blameable;
use App\Traits\Scopes;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @mixin IdeHelperService
 */
class Service extends Model
{
    use HasFactory, Scopes, Blameable;
    protected $fillable = [
        'name',
        'description',
        'default_price',
        'company_id',
        'created_by',
    ];
    public function subscriptions()
    {
        return $this->hasMany(Subscription::class);
    }
    public function company()
    {
        return $this->belongsTo(Company::class);
    }
    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
