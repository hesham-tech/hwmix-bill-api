<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class TaskAssignment extends Model
{
    protected $guarded = [];

    /**
     * The task this assignment belongs to.
     */
    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }

    /**
     * The assignable model (User or TaskGroup).
     */
    public function assignable(): MorphTo
    {
        return $this->morphTo();
    }
}
