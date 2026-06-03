<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

use App\Traits\LogsActivity;

/**
 * كلاس نموذج مرفقات المهام (TaskAttachment) لربط الملفات المرفوعة بالمهام الخاصة بها.
 */
class TaskAttachment extends Model
{
    use LogsActivity;
    protected $guarded = [];

    /**
     * The task this attachment belongs to.
     */
    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }

    /**
     * The user who uploaded the attachment.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
