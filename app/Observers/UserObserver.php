<?php

namespace App\Observers;

use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Support\Facades\Log as LogFacade;
use Illuminate\Support\Facades\Auth;
use Jenssegers\Agent\Agent;

class UserObserver
{
    public function updated(User $user): void
    {
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
    }
}
