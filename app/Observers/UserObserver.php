<?php

namespace App\Observers;

use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Support\Facades\Log as LogFacade;
use Illuminate\Support\Facades\Auth;
use Jenssegers\Agent\Agent;

class UserObserver
{
    /**
     * Handle the User "creating" event.
     */
    public function creating(User $user): void
    {
        // إذا لم يتم تحديد فرع يدوياً، نستخدم الفرع الافتراضي للشركة المرتبط بها
        if (empty($user->branch_id) && !empty($user->active_company_id)) {
            $defaultBranch = \Modules\Companies\Models\Branch::where('company_id', $user->active_company_id)
                ->where('is_default', true)
                ->first();
            
            if ($defaultBranch) {
                $user->branch_id = $defaultBranch->id;
            }
        }
    }

    public function updated(User $user): void
    {
        $companyUserTable = (new \App\Models\CompanyUser())->getTable();
        $hasColumn = function ($column) use ($companyUserTable) {
            return \Illuminate\Support\Facades\Schema::hasColumn($companyUserTable, $column);
        };

        // [تزامن البيانات]: إذا تغير الاسم العالمي، نقوم بتحديث الحقول المقابلة في سجلات الشركات إذا كانت فارغة
        if ($user->isDirty(['full_name', 'nickname'])) {
            $user->companyUsers()->each(function ($companyUser) use ($user, $hasColumn) {
                $updateData = [];
                if ($user->isDirty('full_name') && $hasColumn('full_name_in_company') && empty($companyUser->full_name_in_company)) {
                    $updateData['full_name_in_company'] = $user->full_name;
                }
                if ($user->isDirty('nickname') && $hasColumn('nickname_in_company') && empty($companyUser->nickname_in_company)) {
                    $updateData['nickname_in_company'] = $user->nickname;
                }

                if (!empty($updateData)) {
                    $companyUser->update($updateData);
                }
            });
        }

        // [تزامن بيانات الهوية]: تحديث حقول الكاش (phone, email, username) في جميع الشركات
        $identityData = [];
        if ($user->isDirty('phone') && $hasColumn('user_phone'))
            $identityData['user_phone'] = $user->phone;
        if ($user->isDirty('email') && $hasColumn('user_email'))
            $identityData['user_email'] = $user->email;
        if ($user->isDirty('username') && $hasColumn('user_username'))
            $identityData['user_username'] = $user->username;
 
        if (!empty($identityData)) {
            $user->companyUsers()->update($identityData);
        }
    }
}
