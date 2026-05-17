<?php

namespace Modules\Inventory\Models;

use App\Traits\Blameable;
use App\Traits\Scopes;
use App\Traits\LogsActivity;
use App\Traits\RolePermissions;
use App\Traits\FilterableByCompany;
use App\Traits\FilterableByBranch;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\User;
use App\Models\Company;

/**
 * موديل المخزون (Stock) - موديول المخازن
 */
class Stock extends Model
{
    use HasFactory, Scopes, LogsActivity, RolePermissions, Blameable, FilterableByCompany, FilterableByBranch;

    protected $fillable = [
        'quantity',
        'reserved',
        'min_quantity',
        'cost',
        'batch',
        'expiry',
        'loc',
        'status',
        'variant_id',
        'warehouse_id',
        'company_id',
        'created_by',
        'updated_by',
        'branch_id',
    ];

    public function logLabel()
    {
        return "المخزون ({$this->variant?->sku}) - كمية: {$this->quantity}";
    }

    protected $casts = [
        'quantity' => 'integer',
        'reserved' => 'integer',
        'min_quantity' => 'integer',
        'cost' => 'decimal:2',
        'expiry' => 'datetime',
    ];

    protected static function booted()
    {
        static::creating(function ($stock) {
            if (empty($stock->batch)) {
                $stock->batch = 'B-' . now()->format('Ymd') . '-' . rand(1000, 9999);
            }

            $stock->company_id = $stock->company_id ?? auth()->user()->active_company_id ?? null;
            $stock->branch_id = $stock->branch_id ?? config('app.active_branch_id') ?? auth()->user()->branch_id ?? null;
        });
    }

    public function variant()
    {
        return $this->belongsTo(ProductVariant::class, 'variant_id');
    }

    public function warehouse()
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
