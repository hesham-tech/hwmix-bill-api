<?php

namespace Modules\Notification\Actions;

//   أكشن لحفظ أو تحديث إعدادات حساب الواتساب (Meta Cloud API) الخاص بالشركة مع العزل الكامل وضمان وجود حساب افتراضي واحد للشركة.

use Modules\Core\Actions\BaseAction;
use Modules\Notification\Models\WhatsAppSetting;
use Modules\Notification\DTOs\WhatsAppSettingDTO;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class SaveWhatsAppSettingAction extends BaseAction
{
    public function handle(array $data = []): WhatsAppSetting
    {
        $this->authorize('companies.update_self');

        $companyId = Auth::user()->active_company_id;

        $dto = WhatsAppSettingDTO::fromRequest($data);

        return DB::transaction(function () use ($companyId, $dto) {
            $id = $dto->id;
            $isDefault = $dto->is_default;

            // التحقق مما إذا كان هذا هو الحساب الوحيد للشركة، لجعله افتراضياً تلقائياً
            $hasOtherConfigs = WhatsAppSetting::where('company_id', $companyId)
                ->when($id, function ($q) use ($id) {
                    return $q->where('id', '!=', $id);
                })
                ->exists();

            if (!$hasOtherConfigs) {
                $isDefault = true;
            }

            // تحضير مصفوفة البيانات للحفظ من الـ DTO
            $settingData = $dto->toArray();
            $settingData['company_id'] = $companyId;
            $settingData['is_default'] = $isDefault;

            if (!Auth::user()->hasPermissionTo(perm_key('admin.super'))) {
                $settingData['is_global'] = false;
            }

            if ($id) {
                if (Auth::user()->hasPermissionTo(perm_key('admin.super'))) {
                    $setting = WhatsAppSetting::findOrFail($id);
                } else {
                    $setting = WhatsAppSetting::where('company_id', $companyId)->findOrFail($id);
                }
                $setting->update($settingData);
            } else {
                $setting = WhatsAppSetting::create($settingData);
            }

            // إذا تم تعيين هذا الحساب كافتراضي، نقوم بإلغاء علامة الافتراضي من بقية حسابات الشركة للواتساب
            if ($setting->is_default) {
                WhatsAppSetting::where('company_id', $companyId)
                    ->where('id', '!=', $setting->id)
                    ->update(['is_default' => false]);
            }

            return $setting;
        });
    }
}
