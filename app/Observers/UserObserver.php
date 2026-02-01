<?php

namespace App\Observers;

use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Support\Facades\Log as LogFacade;
use Illuminate\Support\Facades\Auth;
use Jenssegers\Agent\Agent;

class UserObserver
{
    private $agent;

    public function __construct()
    {
        $this->agent = new Agent();
    }

    public function created(User $user): void
    {
        $creator = auth()->user();
        ActivityLog::create([
            'action' => 'created',
            'subject_type' => get_class($user),
            'subject_id' => $user->id,
            'old_values' => null,
            'new_values' => json_encode($user),
            'user_id' => auth()->id(),
            'created_by' => auth()->id(),
            'ip_address' => request()->ip(),
            'user_agent' => $this->agent->browser() . ' '
                . $this->agent->version($this->agent->browser())
                . ' (' . $this->agent->platform()
                . ' ' . $this->agent->version($this->agent->platform()) . ')',
            'url' => request()->getRequestUri(),
            'description' => $creator
                ? 'قام المستخدم ' . $creator->nickname . ' بإنشاء حساب جديد باسم ' . $user->nickname
                : 'تم إنشاء حساب جديد باسم ' . $user->nickname . ' (عن طريق النظام/التسجيل)',
        ]);
    }

    public function updated(User $user): void
    {
        LogFacade::info('User Updated: ' . $user->nickname);

        // [تزامن البيانات]: إذا تغير الاسم العالمي، نقوم بتحديث الحقول المقابلة في سجلات الشركات إذا كانت فارغة
        if ($user->isDirty(['full_name', 'nickname'])) {
            $user->companyUsers()->each(function ($companyUser) use ($user) {
                $updateData = [];
                if ($user->isDirty('full_name') && empty($companyUser->full_name_in_company)) {
                    $updateData['full_name_in_company'] = $user->full_name;
                }
                if ($user->isDirty('nickname') && empty($companyUser->nickname_in_company)) {
                    $updateData['nickname_in_company'] = $user->nickname;
                }

                if (!empty($updateData)) {
                    $companyUser->update($updateData);
                }
            });
        }

        // [تزامن بيانات الهوية]: تحديث حقول الكاش (phone, email, username) في جميع الشركات
        $identityData = [];
        if ($user->isDirty('phone'))
            $identityData['user_phone'] = $user->phone;
        if ($user->isDirty('email'))
            $identityData['user_email'] = $user->email;
        if ($user->isDirty('username'))
            $identityData['user_username'] = $user->username;

        if (!empty($identityData)) {
            $user->companyUsers()->update($identityData);
        }

        $creator = auth()->user();
        ActivityLog::create([
            'action' => 'updated',
            'subject_type' => get_class($user),
            'subject_id' => $user->id,
            'old_values' => json_encode($user->getOriginal()),
            'new_values' => json_encode($user),
            'user_id' => auth()->id(),
            'created_by' => auth()->id(),
            'ip_address' => request()->ip(),
            'user_agent' => $this->agent->browser() . ' '
                . $this->agent->version($this->agent->browser())
                . ' (' . $this->agent->platform()
                . ' ' . $this->agent->version($this->agent->platform()) . ')',
            'url' => request()->getRequestUri(),
            'description' => $creator
                ? 'قام المستخدم ' . $creator->nickname . ' بتحديث بيانات المستخدم ' . $user->nickname . ' (البريد الإلكتروني: ' . $user->email . ') ' . 'في تاريخ ' . now()->format('Y-m-d H:i:s') . '. تم تعديل البيانات بنجاح.'
                : 'تم تحديث بيانات المستخدم ' . $user->nickname . ' (تحديث تلقائي/نظامي)',
        ]);
    }

    public function deleted(User $user): void
    {
        LogFacade::info('User Deleted: ' . $user->nickname);
        $creator = Auth::user();
        ActivityLog::create([
            'action' => 'deleted',
            'subject_type' => get_class($user),
            'subject_id' => $user->id,
            'old_values' => json_encode($user),
            'new_values' => null,
            'user_id' => Auth::id(),
            'created_by' => Auth::id(),
            'ip_address' => request()->ip(),
            'user_agent' => $this->agent->browser() . ' '
                . $this->agent->version($this->agent->browser())
                . ' (' . $this->agent->platform()
                . ' ' . $this->agent->version($this->agent->platform()) . ')',
            'url' => request()->getRequestUri(),
            'description' => $creator
                ? 'قام المستخدم ' . $creator->nickname . ' بحذف الحساب الخاص بالمستخدم ' . $user->nickname . ' بالبريد الإلكتروني ' . $user->email . ' في تاريخ ' . now()->format('Y-m-d H:i:s') . ' من العنوان IP ' . request()->ip() . '.'
                : 'تم حذف الحساب الخاص بالمستخدم ' . $user->nickname . ' (حذف تلقائي/نظامي)',
        ]);
    }

    public function restored(User $user): void
    {
        LogFacade::info('User Restored: ' . $user->nickname);
        $creator = Auth::user();
        ActivityLog::create([
            'action' => 'restored',
            'subject_type' => get_class($user),
            'subject_id' => $user->id,
            'old_values' => null,
            'new_values' => json_encode($user),
            'user_id' => Auth::id(),
            'created_by' => Auth::id(),
            'ip_address' => request()->ip(),
            'user_agent' => $this->agent->browser() . ' '
                . $this->agent->version($this->agent->browser())
                . ' (' . $this->agent->platform()
                . ' ' . $this->agent->version($this->agent->platform()) . ')',
            'url' => request()->getRequestUri(),
            'description' => $creator
                ? 'قام المستخدم ' . $creator->nickname . ' باستعادة حساب المستخدم ' . $user->nickname . ' (البريد الإلكتروني: ' . $user->email . ') ' . 'في تاريخ ' . now()->format('Y-m-d H:i:s') . '.'
                : 'تم استعادة حساب المستخدم ' . $user->nickname . ' (استعادة تلقائية/نظامية)',
        ]);
    }

    public function forceDeleted(User $user): void
    {
        LogFacade::info('User Force Deleted: ' . $user->nickname);
        $creator = Auth::user();
        ActivityLog::create([
            'action' => 'force_deleted',
            'subject_type' => get_class($user),
            'subject_id' => $user->id,
            'old_values' => json_encode($user),
            'new_values' => null,
            'user_id' => Auth::id(),
            'created_by' => Auth::id(),
            'ip_address' => request()->ip(),
            'user_agent' => $this->agent->browser() . ' '
                . $this->agent->version($this->agent->browser())
                . ' (' . $this->agent->platform()
                . ' ' . $this->agent->version($this->agent->platform()) . ')',
            'url' => request()->getRequestUri(),
            'description' => $creator
                ? 'قام المستخدم ' . $creator->nickname . ' بحذف حساب المستخدم ' . $user->nickname . ' (البريد الإلكتروني: ' . $user->email . ') ' . 'بشكل نهائي في تاريخ ' . now()->format('Y-m-d H:i:s') . '. هذه العملية تمت من العنوان IP ' . request()->ip() . '.'
                : 'تم حذف حساب المستخدم ' . $user->nickname . ' بشكل نهائي (حذف تلقائي/نظامي)',
        ]);
    }
}
