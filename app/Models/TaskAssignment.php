<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

use App\Traits\LogsActivity;

/**
 * كلاس نموذج تعيينات المهام (TaskAssignment) لربط المهام بالمستخدمين أو المجموعات المكلفة بها.
 */
class TaskAssignment extends Model
{
    use LogsActivity;
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
