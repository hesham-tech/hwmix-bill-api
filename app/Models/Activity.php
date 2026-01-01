<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Request;

class Activity extends Model
{
    // Action constants
    const ACTION_CREATED = 'created';
    const ACTION_UPDATED = 'updated';
    const ACTION_DELETED = 'deleted';
    const ACTION_VIEWED = 'viewed';
    const ACTION_EXPORTED = 'exported';
    const ACTION_EMAILED = 'emailed';
    const ACTION_PAID = 'paid';
    const ACTION_CANCELLED = 'cancelled';
    const ACTION_RESTORED = 'restored';

    protected $table = 'activity_logs';

    protected $fillable = [
        'user_id',
        'company_id',
        'subject_type',
        'subject_id',
        'action',
        'description',
        'old_values',
        'new_values',
        'ip_address',
        'user_agent',
        'metadata',
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
        return $this->morphTo();
    }

    /**
     * Log an activity
     */
    public static function log(array $attributes)
    {
        $user = Auth::user();

        $defaults = [
            'user_id' => $user?->id,
            'company_id' => $user?->company_id,
            'ip_address' => Request::ip(),
            'user_agent' => Request::userAgent(),
        ];

        // If subject is provided as object, extract type and id
        if (isset($attributes['subject']) && is_object($attributes['subject'])) {
            $subject = $attributes['subject'];
            $attributes['subject_type'] = get_class($subject);
            $attributes['subject_id'] = $subject->id;
            unset($attributes['subject']);
        }

        return self::create(array_merge($defaults, $attributes));
    }

    /**
     * Log model created
     */
    public static function logCreated($model, string $description = null)
    {
        return self::log([
            'action' => self::ACTION_CREATED,
            'description' => $description ?? "تم إنشاء " . class_basename($model),
            'subject' => $model,
            'new_values' => $model->toArray(),
        ]);
    }

    /**
     * Log model updated
     */
    public static function logUpdated($model, string $description = null)
    {
        return self::log([
            'action' => self::ACTION_UPDATED,
            'description' => $description ?? "تم تعديل " . class_basename($model),
            'subject' => $model,
            'old_values' => $model->getOriginal(),
            'new_values' => $model->getChanges(),
        ]);
    }

    /**
     * Log model deleted
     */
    public static function logDeleted($model, string $description = null)
    {
        return self::log([
            'action' => self::ACTION_DELETED,
            'description' => $description ?? "تم حذف " . class_basename($model),
            'subject' => $model,
            'old_values' => $model->toArray(),
        ]);
    }

    /**
     * Scope: Filter by action
     */
    public function scopeAction($query, string $action)
    {
        return $query->where('action', $action);
    }

    /**
     * Scope: Filter by subject
     */
    public function scopeForSubject($query, $subject)
    {
        return $query->where([
            'subject_type' => get_class($subject),
            'subject_id' => $subject->id,
        ]);
    }

    /**
     * Scope: Filter by user
     */
    public function scopeByUser($query, $userId)
    {
        return $query->where('user_id', $userId);
    }

    /**
     * Scope: Filter by date range
     */
    public function scopeDateRange($query, $from, $to)
    {
        return $query->whereBetween('created_at', [$from, $to]);
    }

    /**
     * Get formatted changes
     */
    public function getChangesAttribute()
    {
        if (!$this->old_values || !$this->new_values) {
            return [];
        }

        $changes = [];
        foreach ($this->new_values as $key => $newValue) {
            $oldValue = $this->old_values[$key] ?? null;
            if ($oldValue != $newValue) {
                $changes[$key] = [
                    'from' => $oldValue,
                    'to' => $newValue,
                ];
            }
        }

        return $changes;
    }
}
