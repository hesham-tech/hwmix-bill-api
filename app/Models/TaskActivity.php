<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

use App\Traits\LogsActivity;

/**
 * كلاس نموذج سجلات حركة المهام (TaskActivity) لتسجيل التعليقات والتغييرات داخل كل مهمة.
 */
class TaskActivity extends Model
{
    use LogsActivity;
    protected $guarded = [];

    protected $casts = [
        'metadata' => 'json',
    ];

    /**
     * The task this activity belongs to.
     */
    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }

    /**
     * The user who performed the activity.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
