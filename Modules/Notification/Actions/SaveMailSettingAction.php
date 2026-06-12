<?php

namespace Modules\Notification\Actions;

//   أكشن لحفظ أو تحديث إعدادات البريد الإلكتروني (SMTP/Mailgun) الخاصة بالشركة مع عزل كامل لكل شركة.

use Modules\Core\Actions\BaseAction;
use Modules\Notification\Models\MailSetting;
use Illuminate\Support\Facades\Auth;

class SaveMailSettingAction extends BaseAction
{
    public function handle(array $data = []): MailSetting
    {
        $this->authorize('companies.update_self');

        $companyId = Auth::user()->active_company_id;
        $id = $data['id'] ?? null;

        $isDefault = isset($data['is_default']) ? (bool) $data['is_default'] : false;

        // التحقق مما إذا كان هذا هو الحساب الوحيد للشركة، لجعله افتراضياً تلقائياً
        $hasOtherConfigs = MailSetting::where('company_id', $companyId)
            ->when($id, function ($q) use ($id) {
                return $q->where('id', '!=', $id);
            })
            ->exists();

        if (!$hasOtherConfigs) {
            $isDefault = true;
        }

        $settingData = [
            'title' => $data['title'] ?? 'خادم البريد الافتراضي',
            'mail_transport' => $data['mail_transport'],
            'mail_host' => $data['mail_host'] ?? null,
            'mail_port' => $data['mail_port'] ?? null,
            'mail_username' => $data['mail_username'] ?? null,
            'mail_encryption' => $data['mail_encryption'] ?? null,
            'mail_from_address' => $data['mail_from_address'] ?? null,
            'mail_from_name' => $data['mail_from_name'] ?? null,
            'is_active' => isset($data['is_active']) ? (bool) $data['is_active'] : true,
            'is_default' => $isDefault,
            'company_id' => $companyId,
        ];

        if (isset($data['is_global']) && Auth::user()->hasPermissionTo(perm_key('admin.super'))) {
            $settingData['is_global'] = (bool) $data['is_global'];
        }

        // نقوم بإضافة وتشفير كلمة السر فقط في حال تمريرها لتجنب الكتابة فوقها بقيمة فارغة
        if (!empty($data['mail_password'])) {
            $settingData['mail_password'] = $data['mail_password'];
        }

        if ($id) {
            if (Auth::user()->hasPermissionTo(perm_key('admin.super'))) {
                $setting = MailSetting::findOrFail($id);
            } else {
                $setting = MailSetting::where('company_id', $companyId)->findOrFail($id);
            }
            $setting->update($settingData);
        } else {
            $setting = MailSetting::create($settingData);
        }

        // إذا تم تعيين هذا الحساب كافتراضي، نقوم بإلغاء علامة الافتراضي من بقية حسابات الشركة
        if ($setting->is_default) {
            MailSetting::where('company_id', $companyId)
                ->where('id', '!=', $setting->id)
                ->update(['is_default' => false]);
        }

        return $setting;
    }
}
