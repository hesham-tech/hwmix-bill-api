<?php

namespace App\Traits;

use App\Models\ActivityLog;
use Illuminate\Support\Facades\Auth;

trait LogsActivity
{
    /**
     * Boot the trait and register model observers.
     */
    public static function bootLogsActivity()
    {
        static::created(function ($model) {
            if ($model->shouldLog('created')) {
                $model->logCreated($model->getLogLabel());
            }
        });

        static::updated(function ($model) {
            if ($model->shouldLog('updated')) {
                $model->logUpdated($model->getLogLabel());
            }
        });

        static::deleted(function ($model) {
            if ($model->shouldLog('deleted')) {
                if (method_exists($model, 'isForceDeleting') && $model->isForceDeleting()) {
                    $model->logForceDeleted($model->getLogLabel());
                } else {
                    $model->logDeleted($model->getLogLabel());
                }
            }
        });

        if (in_array('Illuminate\Database\Eloquent\SoftDeletes', class_uses_recursive(static::class))) {
            static::restored(function ($model) {
                if ($model->shouldLog('restored')) {
                    $model->logRestored($model->getLogLabel());
                }
            });

            static::forceDeleted(function ($model) {
                if ($model->shouldLog('forceDeleted')) {
                    $model->logForceDeleted($model->getLogLabel());
                }
            });
        }
    }

    /**
     * Determine if an activity should be logged for the given action.
     */
    protected function shouldLog($action)
    {
        // Can be overridden in models to disable logging or filter specific actions
        return property_exists($this, 'doNotLog') ? !in_array($action, $this->doNotLog) : true;
    }

    /**
     * Get the label for the model to be used in logs.
     */
    protected function getLogLabel()
    {
        if (method_exists($this, 'logLabel')) {
            return $this->logLabel();
        }

        // Try to find a sensible label
        $name = $this->name ?? $this->title ?? $this->nickname ?? $this->username ?? $this->id;
        $classBasename = class_basename($this);

        return "{$classBasename} #{$this->id} ({$name})";
    }

    /**
     * Centralized method to record activity.
     */
    private function recordActivity($action, $description, $oldValues = null, $newValues = null)
    {
        $user = Auth::user();
        if (!$user)
            return;

        // Filtering sensitive fields
        $sensitiveFields = ['password', 'remember_token', 'token', 'access_token', 'secret', 'key'];

        $filter = function ($data) use ($sensitiveFields) {
            if (!is_array($data))
                return $data;
            return array_diff_key($data, array_flip($sensitiveFields));
        };

        ActivityLog::create([
            'action' => $action,
            'subject_type' => get_class($this),
            'subject_id' => $this->id,
            'old_values' => $filter($oldValues),
            'new_values' => $filter($newValues),
            'user_id' => $user->id,
            'created_by' => $user->id,
            'company_id' => $user->company_id,
            'ip_address' => request()->ip(),
            'user_agent' => request()->header('User-Agent'),
            'url' => request()->getRequestUri(),
            'description' => $description,
        ]);
    }

    public function logCreated($text)
    {
        $user = Auth::user();
        if (!$user)
            return;
        $description = 'قام المستخدم ' . ($user->nickname ?? $user->name) . ' بانشاء ' . $text;
        $this->recordActivity('انشاء', $description, null, $this->getAttributes());
    }

    public function logUpdated($text)
    {
        $user = Auth::user();
        if (!$user)
            return;
        $changes = $this->getChanges();
        $oldValues = array_intersect_key($this->getOriginal(), $changes);

        $description = 'قام المستخدم ' . ($user->nickname ?? $user->name) . ' بتعديل ' . $text;
        $this->recordActivity('تعديل', $description, $oldValues, $changes);
    }

    public function logDeleted($text)
    {
        $user = Auth::user();
        if (!$user)
            return;
        $description = 'قام المستخدم ' . ($user->nickname ?? $user->name) . ' بحذف ' . $text;
        $this->recordActivity('حذف', $description, $this->getAttributes(), null);
    }

    /**
     * تسجيل عملية إلغاء نموذج.
     */
    public function logCanceled($text)
    {
        $changes = ['status' => 'canceled'];
        $oldValues = array_intersect_key($this->getOriginal(), $changes);

        $description = 'قام المستخدم ' . (Auth::user()->nickname ?? Auth::user()->name) . ' بإلغاء ' . $text;
        $this->recordActivity('إلغاء', $description, $oldValues, $changes);
    }

    public function logRestored($text)
    {
        $description = 'قام المستخدم ' . (Auth::user()->nickname ?? Auth::user()->name) . ' باستعادة ' . $text;
        $this->recordActivity('استعادة', $description, null, $this->getAttributes());
    }

    public function logForceDeleted($text)
    {
        $description = 'قام المستخدم ' . (Auth::user()->nickname ?? Auth::user()->name) . ' بحذف ' . $text . ' حذف نهائي';
        $this->recordActivity('حذف نهائي', $description, $this->getAttributes(), null);
    }
}
