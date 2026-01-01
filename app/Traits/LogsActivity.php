<?php

namespace App\Traits;

use App\Models\ActivityLog;
use Illuminate\Support\Facades\Auth;

trait LogsActivity
{
    public function logCreated($text)
    {
        $user = Auth::user();
        ActivityLog::create([
            'action' => 'انشاء',
            'subject_type' => get_class($this),
            'subject_id' => $this->id,
            'old_values' => null,
            'new_values' => json_encode($this->getAttributes()),
            'user_id' => $user->id,
            'created_by' => $user->id,
            'company_id' => $user->company_id,
            'ip_address' => request()->ip(),
            'user_agent' => request()->header('User-Agent'),
            'url' => request()->getRequestUri(),
            'description' => ' قام المستخدم ' . Auth::user()->nickname
                . $text,
        ]);
    }

    public function logUpdated($text)
    {
        $user = Auth::user();
        ActivityLog::create([
            'action' => 'تعديل',
            'subject_type' => get_class($this),
            'subject_id' => $this->id,
            'old_values' => json_encode($this->getOriginal()),
            'new_values' => json_encode($this->getChanges()),
            'user_id' => $user->id,
            'created_by' => $user->id,
            'company_id' => Auth::user()->company_id,
            'ip_address' => request()->ip(),
            'user_agent' => request()->header('User-Agent'),
            'url' => request()->getRequestUri(),
            'description' => 'قام المستخدم ' . Auth::user()->nickname
                . ' بتعديل ' . $text,
        ]);
    }

    public function logDeleted($text)
    {
        $user = Auth::user();
        ActivityLog::create([
            'action' => 'حذف',
            'subject_type' => get_class($this),
            'subject_id' => $this->id,
            'old_values' => json_encode($this->getAttributes()),
            'new_values' => null,
            'user_id' => $user->id,
            'created_by' => $user->id,
            'company_id' => Auth::user()->company_id,
            'ip_address' => request()->ip(),
            'user_agent' => request()->header('User-Agent'),
            'url' => request()->getRequestUri(),
            'description' => 'قام المستخدم ' . Auth::user()->nickname
                . ' بحذف ' . $text,
        ]);
    }

    /**
     * تسجيل عملية إلغاء نموذج.
     *
     * @param string $text وصف الإلغاء.
     * @return void
     */
    public function logCanceled($text)
    {
        $user = Auth::user();
        ActivityLog::create([
            'action' => 'إلغاء',
            'subject_type' => get_class($this),
            'subject_id' => $this->id,
            'old_values' => json_encode($this->getOriginal()), // تسجيل الحالة قبل الإلغاء
            'new_values' => json_encode(['status' => 'canceled']), // تسجيل الحالة الجديدة
            'user_id' => $user->id,
            'created_by' => $user->id,
            'company_id' => Auth::user()->company_id,
            'ip_address' => request()->ip(),
            'user_agent' => request()->header('User-Agent'),
            'url' => request()->getRequestUri(),
            'description' => 'قام المستخدم ' . Auth::user()->nickname
                . ' بإلغاء ' . $text,
        ]);
    }

    public function logRestored($text)
    {
        $user = Auth::user();
        ActivityLog::create([
            'action' => 'استعادة',
            'subject_type' => get_class($this),
            'subject_id' => $this->id,
            'old_values' => null,
            'new_values' => json_encode($this->getAttributes()),
            'user_id' => $user->id,
            'created_by' => $user->id,
            'company_id' => Auth::user()->company_id,
            'ip_address' => request()->ip(),
            'user_agent' => request()->header('User-Agent'),
            'url' => request()->getRequestUri(),
            'description' => 'قام المستخدم ' . Auth::user()->nickname
                . ' باستعادة ' . $text,
        ]);
    }

    public function logForceDeleted($text)
    {
        $user = Auth::user();
        ActivityLog::create([
            'action' => 'حذف نهائي',
            'subject_type' => get_class($this),
            'subject_id' => $this->id,
            'old_values' => json_encode($this->getAttributes()),
            'new_values' => null,
            'user_id' => $user->id,
            'created_by' => $user->id,
            'company_id' => Auth::user()->company_id,
            'ip_address' => request()->ip(),
            'user_agent' => request()->header('User-Agent'),
            'url' => request()->getRequestUri(),
            'description' => 'قام المستخدم ' . Auth::user()->nickname
                . ' بحذف ' . $text . ' حذف نهائي ',
        ]);
    }
}
