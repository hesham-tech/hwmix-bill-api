<?php

namespace App\Models;

use App\Traits\Scopes;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ActivityLog extends Model
{
    use HasFactory, Scopes, \App\Traits\FilterableByBranch;

    protected $table = 'activity_logs';

    /**
     * Prevent logging for this model to avoid infinite recursion.
     */
    protected $doNotLog = ['created', 'updated', 'deleted'];

    protected $fillable = [
        'action',
        'model',
        'row_id',
        'data_old',
        'data_new',
        'old_values',
        'new_values',
        'description',
        'user_id',
        'created_by',
        'company_id',
        'user_agent',
        'ip_address',
        'url',
        'branch_id',
    ];

    protected $casts = [
        'old_values' => 'array',
        'new_values' => 'array',
        'metadata' => 'array',
    ];

    /**
     * Relationships
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function subject()
    {
        return $this->morphTo(__FUNCTION__, 'model', 'row_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function branch()
    {
        return $this->belongsTo(\Modules\Companies\Models\Branch::class);
    }
}
