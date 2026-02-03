<?php

namespace App\Models;

use App\Traits\LogsActivity;
use App\Traits\Blameable;
use App\Traits\HasImages;
use App\Traits\Scopes;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Brand extends Model
{
    use HasFactory, Scopes, Blameable, LogsActivity, HasImages;

    protected $fillable = ['name', 'slug', 'description', 'active', 'company_id', 'created_by'];

    public function products()
    {
        return $this->hasMany(Product::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function company()
    {
        return $this->belongsTo(Company::class);
    }


    /**
     * Label for activity logs.
     */
    public function logLabel()
    {
        return "الماركة ({$this->name})";
    }
}
