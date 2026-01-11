<?php

namespace App\Models;

use App\Traits\Scopes;
use App\Traits\Blameable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

use App\Traits\LogsActivity;

/**
 * @mixin IdeHelperWarehouse
 */
class Warehouse extends Model
{
    use HasFactory, Blameable, Scopes, LogsActivity;

    /**
     * Label for activity logs.
     */
    public function logLabel()
    {
        return "المخزن ({$this->name})";
    }

    protected $guarded = [];

    protected $casts = [
        'capacity' => 'integer',
    ];

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function stocks()
    {
        return $this->hasMany(Stock::class);
    }
}
