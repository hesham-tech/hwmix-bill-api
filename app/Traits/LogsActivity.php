<?php

namespace App\Traits;

use App\Models\ActivityLog;
use Illuminate\Support\Facades\Auth;

// سمة (Trait) لتسجيل الأنشطة وتتبع التعديلات والعمليات الحساسة على الكيانات تلقائياً
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

        // Filtering sensitive fields
        $sensitiveFields = ['password', 'remember_token', 'token', 'access_token', 'secret', 'key'];

        $filter = function ($data) use ($sensitiveFields) {
            if (!is_array($data))
                return $data;
            return array_diff_key($data, array_flip($sensitiveFields));
        };

        // Determine user IDs and company ID
        $userId = $user?->id ?? ($this->created_by ?? $this->user_id ?? null);
        $companyId = $user?->active_company_id ?? ($this->company_id ?? null);
        $branchId = config('app.active_branch_id') ?? $user?->branch_id ?? ($this->branch_id ?? null);

        \App\Jobs\LogActivityJob::dispatch([
            'action' => $action,
            'model' => get_class($this),
            'row_id' => $this->id,
            'old_values' => $filter($oldValues),
            'new_values' => $filter($newValues),
            'user_id' => $userId,
            'created_by' => $userId,
            'company_id' => $companyId,
            'branch_id' => $branchId,
            'ip_address' => request()->ip(),
            'user_agent' => request()->header('User-Agent'),
            'url' => request()->getRequestUri(),
            'description' => $description,
        ]);
    }

    public function logCreated($text)
    {
        $user = Auth::user();
        $userName = $user ? ($user->nickname ?? $user->name) : 'النظام';
        $description = 'قام المستخدم ' . $userName . ' بانشاء ' . $text;
        $this->recordActivity('انشاء', $description, null, $this->getAttributes());
    }

    public function logUpdated($text)
    {
        $user = Auth::user();
        $userName = $user ? ($user->nickname ?? $user->name) : 'النظام';
        $changes = $this->getChanges();
        $oldValues = array_intersect_key($this->getOriginal(), $changes);

        $description = 'قام المستخدم ' . $userName . ' بتعديل ' . $text;
        $this->recordActivity('تعديل', $description, $oldValues, $changes);
    }

    public function logDeleted($text)
    {
        $user = Auth::user();
        $userName = $user ? ($user->nickname ?? $user->name) : 'النظام';
        $description = 'قام المستخدم ' . $userName . ' بحذف ' . $text;
        $this->recordActivity('حذف', $description, $this->getAttributes(), null);
    }

    /**
     * تسجيل عملية إلغاء نموذج.
     */
    public function logCanceled($text)
    {
        $changes = ['status' => 'canceled'];
        $oldValues = array_intersect_key($this->getOriginal(), $changes);

        $user = Auth::user();
        $userName = $user ? ($user->nickname ?? $user->name) : 'النظام';
        $description = 'قام المستخدم ' . $userName . ' بإلغاء ' . $text;
        $this->recordActivity('إلغاء', $description, $oldValues, $changes);
    }

    public function logRestored($text)
    {
        $user = Auth::user();
        $userName = $user ? ($user->nickname ?? $user->name) : 'النظام';
        $description = 'قام المستخدم ' . $userName . ' باستعادة ' . $text;
        $this->recordActivity('استعادة', $description, null, $this->getAttributes());
    }

    public function logForceDeleted($text)
    {
        $user = Auth::user();
        $userName = $user ? ($user->nickname ?? $user->name) : 'النظام';
        $description = 'قام المستخدم ' . $userName . ' بحذف ' . $text . ' حذف نهائي';
        $this->recordActivity('حذف نهائي', $description, $this->getAttributes(), null);
    }
}
