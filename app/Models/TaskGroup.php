<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Traits\FilterableByCompany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class TaskGroup extends Model
{
    use SoftDeletes, FilterableByCompany;

    protected $guarded = [];

    /**
     * Users in this group.
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'task_group_user');
    }

    /**
     * User who created the group.
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
