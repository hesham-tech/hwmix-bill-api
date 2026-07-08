<?php

namespace App\Observers;

// مراقب لتسجيل جميع عمليات إنشاء وتعديل وحذف أنواع العلاقات التجارية بقاعدة البيانات.

use App\Models\ActivityLog;
use Modules\Companies\Models\RelationType;
use Illuminate\Support\Facades\Auth;

class RelationTypeObserver
{
    /**
     * Handle the RelationType "created" event.
     */
    public function created(RelationType $relationType): void
    {
        $user = Auth::user();
        if (!$user) return;

        ActivityLog::create([
            'action' => 'انشاء',
            'model' => get_class($relationType),
            'row_id' => $relationType->id,
            'data_old' => null,
            'data_new' => json_encode($relationType->getAttributes()),
            'user_id' => $user->id,
            'created_by' => $user->id,
            'company_id' => $user->active_company_id,
            'ip_address' => request()->ip(),
            'user_agent' => request()->header('User-Agent'),
            'url' => request()->getRequestUri(),
            'description' => 'قام المستخدم ' . $user->nickname . ' بإضافة نوع العلاقة الجديد: ' . $relationType->display_name . ' (' . $relationType->code . ')',
        ]);
    }

    /**
     * Handle the RelationType "updated" event.
     */
    public function updated(RelationType $relationType): void
    {
        $user = Auth::user();
        if (!$user) return;

        ActivityLog::create([
            'action' => 'تعديل',
            'model' => get_class($relationType),
            'row_id' => $relationType->id,
            'data_old' => json_encode($relationType->getOriginal()),
            'data_new' => json_encode($relationType->getAttributes()),
            'user_id' => $user->id,
            'created_by' => $user->id,
            'company_id' => $user->active_company_id,
            'ip_address' => request()->ip(),
            'user_agent' => request()->header('User-Agent'),
            'url' => request()->getRequestUri(),
            'description' => 'قام المستخدم ' . $user->nickname . ' بتعديل نوع العلاقة: ' . $relationType->display_name,
        ]);
    }

    /**
     * Handle the RelationType "deleted" event.
     */
    public function deleted(RelationType $relationType): void
    {
        $user = Auth::user();
        if (!$user) return;

        ActivityLog::create([
            'action' => 'حذف',
            'model' => get_class($relationType),
            'row_id' => $relationType->id,
            'data_old' => json_encode($relationType->getRawOriginal()),
            'data_new' => null,
            'user_id' => $user->id,
            'created_by' => $user->id,
            'company_id' => $user->active_company_id,
            'ip_address' => request()->ip(),
            'user_agent' => request()->header('User-Agent'),
            'url' => request()->getRequestUri(),
            'description' => 'قام المستخدم ' . $user->nickname . ' بحذف نوع العلاقة: ' . $relationType->display_name,
        ]);
    }
}
