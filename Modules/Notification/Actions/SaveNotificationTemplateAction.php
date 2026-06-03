<?php

namespace Modules\Notification\Actions;

// تعليق عربي: أكشن لحفظ أو تحديث قوالب الإشعارات الخاصة بالشركة مع ضمان عزل البيانات.

use Modules\Core\Actions\BaseAction;
use Modules\Notification\Models\NotificationTemplate;
use Illuminate\Support\Facades\Auth;

class SaveNotificationTemplateAction extends BaseAction
{
    /**
     * تنفيذ الأكشن لحفظ أو تحديث قالب الإشعار.
     */
    public function handle(array $data = []): NotificationTemplate
    {
        $this->authorize('companies.update_self');

        $companyId = Auth::user()->active_company_id;
        $id = $data['id'] ?? null;

        $templateData = [
            'name' => $data['name'],
            'channel' => $data['channel'],
            'subject' => $data['subject'] ?? null,
            'body' => $data['body'],
            'is_active' => isset($data['is_active']) ? (bool)$data['is_active'] : true,
            'company_id' => $companyId,
        ];

        if (isset($data['is_global']) && Auth::user()->hasPermissionTo(perm_key('admin.super'))) {
            $templateData['is_global'] = (bool)$data['is_global'];
        }

        if ($id) {
            if (Auth::user()->hasPermissionTo(perm_key('admin.super'))) {
                $template = NotificationTemplate::findOrFail($id);
            } else {
                $template = NotificationTemplate::where('company_id', $companyId)->findOrFail($id);
            }
            $template->update($templateData);
        } else {
            $template = NotificationTemplate::create($templateData);
        }

        return $template;
    }
}
