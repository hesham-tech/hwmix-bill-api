<?php

namespace Modules\Inventory\Models;

use App\Traits\Scopes;
use App\Traits\Blameable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Traits\LogsActivity;
use App\Models\Company;
use App\Models\User;
use App\Models\Stock;

/**
 * موديل المستودع - تم نقله لموديول المخازن (Inventory)
 */
class Warehouse extends Model
{
    use HasFactory, Blameable, Scopes, LogsActivity, \App\Traits\FilterableByCompany, \App\Traits\FilterableByBranch;

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
        static::creating(function ($warehouse) {
            $warehouse->company_id = $warehouse->company_id ?? auth()->user()->company_id ?? null;
            $warehouse->branch_id = $warehouse->branch_id ?? config('app.active_branch_id') ?? auth()->user()->branch_id ?? null;
        });

        static::saving(function ($warehouse) {
            // If this is the first warehouse for the branch, make it default
            if ($warehouse->branch_id && static::where('branch_id', $warehouse->branch_id)->count() === 0) {
                $warehouse->is_default = true;
            }

            if ($warehouse->is_default && $warehouse->branch_id) {
                // Set other warehouses for this branch to NOT default
                static::where('branch_id', $warehouse->branch_id)
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
