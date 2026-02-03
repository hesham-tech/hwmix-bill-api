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
        'is_default' => 'boolean',
    ];

    /**
     * The "booted" method of the model.
     */
    protected static function booted()
    {
        static::saving(function ($warehouse) {
            // If this is the first warehouse for the company, make it default
            if (static::where('company_id', $warehouse->company_id)->count() === 0) {
                $warehouse->is_default = true;
            }

            if ($warehouse->is_default) {
                // Set other warehouses for this company to NOT default
                static::where('company_id', $warehouse->company_id)
                    ->where('id', '!=', $warehouse->id)
                    ->update(['is_default' => false]);
            }
        });
    }

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
